import uuid
from datetime import datetime, timezone

from fastapi import APIRouter, HTTPException, Depends, Query

from database import db
from models import TrainingCreate, TrainingUpdate, TrainingReview
from auth import get_current_user, require_admin, get_current_member
from helpers import now_iso, add_history, notify, notify_admins, get_settings

router = APIRouter(prefix="/trainings", tags=["trainings"])

MEMBER_EDITABLE = ("concept", "aanpassing_gevraagd")


def _year_of(date_str, provided):
    if provided:
        return int(provided)
    try:
        return int(str(date_str)[:4])
    except Exception:
        return datetime.now(timezone.utc).year


@router.post("")
async def create_training(data: TrainingCreate, member: dict = Depends(get_current_member),
                          user: dict = Depends(get_current_user)):
    status = data.status if data.status in ("concept", "ingediend") else "ingediend"
    year = _year_of(data.date, data.year)
    doc = {
        "id": str(uuid.uuid4()),
        "member_id": member["id"],
        "member_name": member["name"],
        "year": year,
        "date": data.date,
        "hours": data.hours,
        "organization": data.organization,
        "subject": data.subject,
        "content_explanation": data.content_explanation,
        "speaker": data.speaker,
        "activity_type": data.activity_type,
        "member_remarks": data.member_remarks,
        "points": None,
        "admin_remark": "",
        "status": status,
        "created_at": now_iso(),
        "updated_at": now_iso(),
        "submitted_at": now_iso() if status == "ingediend" else None,
    }
    await db.training_records.insert_one(doc)
    await add_history("training", doc["id"], "aangemaakt", user, None, status)
    if status == "ingediend":
        await notify_admins("training_submitted", "Nieuwe bijscholing ingediend",
                            f"{member['name']} heeft '{data.subject}' ingediend.",
                            {"training_id": doc["id"]})
    doc.pop("_id", None)
    return doc


@router.get("")
async def list_trainings(user: dict = Depends(get_current_user),
                         year: int = Query(None), status: str = Query(None),
                         member_id: str = Query(None), organization: str = Query(None),
                         activity_type: str = Query(None)):
    query = {}
    if user.get("role") != "admin":
        member = await db.members.find_one({"user_id": user["id"]}, {"_id": 0, "id": 1})
        if not member:
            return []
        query["member_id"] = member["id"]
    elif member_id:
        query["member_id"] = member_id
    if year:
        query["year"] = year
    if status:
        query["status"] = status
    if organization:
        query["organization"] = {"$regex": organization, "$options": "i"}
    if activity_type:
        query["activity_type"] = activity_type
    items = await db.training_records.find(query, {"_id": 0}).sort("date", -1).to_list(2000)
    # attach document refs
    for it in items:
        docs = await db.documents.find(
            {"training_id": it["id"], "is_deleted": False}, {"_id": 0}
        ).to_list(50)
        it["documents"] = docs
    return items


async def _get_training_checked(training_id, user):
    tr = await db.training_records.find_one({"id": training_id}, {"_id": 0})
    if not tr:
        raise HTTPException(status_code=404, detail="Bijscholing niet gevonden")
    if user.get("role") != "admin":
        member = await db.members.find_one({"user_id": user["id"]}, {"_id": 0, "id": 1})
        if not member or tr["member_id"] != member["id"]:
            raise HTTPException(status_code=403, detail="Geen toegang")
    return tr


@router.get("/{training_id}")
async def get_training(training_id: str, user: dict = Depends(get_current_user)):
    tr = await _get_training_checked(training_id, user)
    tr["documents"] = await db.documents.find(
        {"training_id": training_id, "is_deleted": False}, {"_id": 0}).to_list(50)
    return tr


@router.get("/{training_id}/history")
async def training_history(training_id: str, user: dict = Depends(get_current_user)):
    await _get_training_checked(training_id, user)
    return await db.status_history.find(
        {"entity_type": "training", "entity_id": training_id}, {"_id": 0}
    ).sort("created_at", 1).to_list(200)


@router.put("/{training_id}")
async def update_training(training_id: str, data: TrainingUpdate, user: dict = Depends(get_current_user)):
    tr = await _get_training_checked(training_id, user)
    if user.get("role") != "admin" and tr["status"] not in MEMBER_EDITABLE:
        raise HTTPException(status_code=400, detail="Deze bijscholing kan niet meer worden gewijzigd")
    updates = {k: v for k, v in data.model_dump().items() if v is not None}
    if updates:
        updates["updated_at"] = now_iso()
        if "date" in updates:
            updates["year"] = _year_of(updates["date"], None)
        await db.training_records.update_one({"id": training_id}, {"$set": updates})
        await add_history("training", training_id, "gewijzigd", user, remark="Gegevens bijgewerkt")
    return await db.training_records.find_one({"id": training_id}, {"_id": 0})


@router.post("/{training_id}/submit")
async def submit_training(training_id: str, user: dict = Depends(get_current_user)):
    tr = await _get_training_checked(training_id, user)
    if tr["status"] not in ("concept", "aanpassing_gevraagd"):
        raise HTTPException(status_code=400, detail="Kan niet worden ingediend")
    await db.training_records.update_one(
        {"id": training_id}, {"$set": {"status": "ingediend", "submitted_at": now_iso(), "updated_at": now_iso()}})
    await add_history("training", training_id, "ingediend", user, tr["status"], "ingediend")
    await notify_admins("training_submitted", "Bijscholing ingediend",
                        f"{tr['member_name']} heeft '{tr['subject']}' ingediend.",
                        {"training_id": training_id})
    return await db.training_records.find_one({"id": training_id}, {"_id": 0})


@router.post("/{training_id}/review")
async def review_training(training_id: str, data: TrainingReview, admin: dict = Depends(require_admin)):
    tr = await db.training_records.find_one({"id": training_id}, {"_id": 0})
    if not tr:
        raise HTTPException(status_code=404, detail="Bijscholing niet gevonden")
    settings = await get_settings()
    member = await db.members.find_one({"id": tr["member_id"]}, {"_id": 0})
    templates = settings.get("email_templates", {})
    updates = {"updated_at": now_iso(), "reviewed_by": admin["name"], "reviewed_at": now_iso()}
    from_status = tr["status"]

    if data.points is not None:
        updates["points"] = data.points
        await add_history("training", training_id, "punten_toegekend", admin,
                          remark=f"{data.points} punt(en) toegekend")
    if data.remark:
        updates["admin_remark"] = data.remark

    action = data.action
    if action == "approve":
        updates["status"] = "goedgekeurd"
        if data.points is not None:
            updates["points"] = data.points
        elif tr.get("points") is None:
            updates["points"] = 0
        await db.training_records.update_one({"id": training_id}, {"$set": updates})
        await add_history("training", training_id, "goedgekeurd", admin, from_status, "goedgekeurd", data.remark)
        if member:
            msg = templates.get("training_approved", "").format(
                name=member["name"], subject=tr["subject"], points=updates.get("points", 0))
            await notify(member["user_id"], "training_approved", "Bijscholing goedgekeurd", msg,
                         {"training_id": training_id})
    elif action == "reject":
        updates["status"] = "afgekeurd"
        await db.training_records.update_one({"id": training_id}, {"$set": updates})
        await add_history("training", training_id, "afgekeurd", admin, from_status, "afgekeurd", data.remark)
        if member:
            msg = templates.get("training_rejected", "").format(
                name=member["name"], subject=tr["subject"], remark=data.remark or "")
            await notify(member["user_id"], "training_rejected", "Bijscholing afgekeurd", msg,
                         {"training_id": training_id})
    elif action == "request_changes":
        updates["status"] = "aanpassing_gevraagd"
        await db.training_records.update_one({"id": training_id}, {"$set": updates})
        await add_history("training", training_id, "aanpassing_gevraagd", admin, from_status,
                          "aanpassing_gevraagd", data.remark)
        if member:
            msg = templates.get("training_changes", "").format(
                name=member["name"], subject=tr["subject"], remark=data.remark or "")
            await notify(member["user_id"], "training_changes", "Aanvullende informatie gevraagd", msg,
                         {"training_id": training_id})
    elif action == "assign_points":
        if "status" not in updates and from_status == "ingediend":
            updates["status"] = "in_beoordeling"
        await db.training_records.update_one({"id": training_id}, {"$set": updates})
    else:
        raise HTTPException(status_code=400, detail="Onbekende actie")

    return await db.training_records.find_one({"id": training_id}, {"_id": 0})
