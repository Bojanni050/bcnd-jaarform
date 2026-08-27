import uuid
from datetime import datetime, timezone

from fastapi import APIRouter, HTTPException, Depends, Query
from fastapi.responses import Response

from database import db
from models import AnnualSubmit, AnnualReview
from auth import get_current_user, require_admin, get_current_member
from helpers import now_iso, add_history, notify, notify_admins, get_settings
from business import build_year_overview, compute_norms
from pdf_generator import generate_annual_pdf
from storage import put_object, get_object, APP_NAME

router = APIRouter(prefix="/annual-forms", tags=["annual-forms"])

LOCKED_STATUSES = ("ingediend", "in_beoordeling", "goedgekeurd")


async def _get_or_create_form(member, year):
    form = await db.annual_forms.find_one({"member_id": member["id"], "year": year}, {"_id": 0})
    if not form:
        form = {
            "id": str(uuid.uuid4()),
            "member_id": member["id"],
            "member_name": member["name"],
            "year": year,
            "status": "concept",
            "deviation_reason": "",
            "submitted_at": None,
            "reviewed_by": None,
            "reviewed_at": None,
            "admin_remark": "",
            "pdf_document_id": None,
            "applied_points_norm": None,
            "applied_consults_norm": None,
            "achieved_points": None,
            "achieved_consults": None,
            "created_at": now_iso(),
            "updated_at": now_iso(),
        }
        await db.annual_forms.insert_one(dict(form))
    return form


@router.get("/overview")
async def overview(year: int = Query(...), member: dict = Depends(get_current_member)):
    return await build_year_overview(member, year)


@router.get("")
async def get_form(year: int = Query(...), user: dict = Depends(get_current_user)):
    if user.get("role") == "admin":
        raise HTTPException(status_code=400, detail="Gebruik het beheerdersoverzicht")
    member = await db.members.find_one({"user_id": user["id"]}, {"_id": 0})
    if not member:
        raise HTTPException(status_code=404, detail="Geen lidprofiel")
    form = await _get_or_create_form(member, year)
    ov = await build_year_overview(member, year)
    trainings = await db.training_records.find(
        {"member_id": member["id"], "year": year}, {"_id": 0}).sort("date", 1).to_list(1000)
    return {"form": form, "overview": ov, "trainings": trainings, "member": member}


@router.post("/{year}/submit")
async def submit_form(year: int, data: AnnualSubmit, user: dict = Depends(get_current_user)):
    member = await db.members.find_one({"user_id": user["id"]}, {"_id": 0})
    if not member:
        raise HTTPException(status_code=404, detail="Geen lidprofiel")
    form = await _get_or_create_form(member, year)
    if form["status"] in ("ingediend", "in_beoordeling", "goedgekeurd"):
        raise HTTPException(status_code=400, detail="Jaarformulier is al ingediend")
    ov = await build_year_overview(member, year)
    if not ov["all_complete"] and not (data.deviation_reason or "").strip():
        raise HTTPException(status_code=400,
                            detail="De norm is niet behaald. Een toelichting is vereist om in te dienen.")
    updates = {
        "status": "ingediend",
        "deviation_reason": data.deviation_reason or "",
        "submitted_at": now_iso(),
        "submitted_by": user["name"],
        "updated_at": now_iso(),
    }
    await db.annual_forms.update_one({"id": form["id"]}, {"$set": updates})
    await add_history("annual_form", form["id"], "ingediend", user, form["status"], "ingediend",
                      "Jaarformulier ingediend" + ("" if ov["all_complete"] else " (norm niet behaald)"))
    await notify_admins("annual_submitted", "Jaarformulier ingediend",
                        f"{member['name']} heeft het jaarformulier {year} ingediend.",
                        {"form_id": form["id"]})
    return await db.annual_forms.find_one({"id": form["id"]}, {"_id": 0})


# ---------- Admin ----------
@router.get("/admin/list")
async def admin_list(admin: dict = Depends(require_admin), year: int = Query(None),
                     status: str = Query(None), member_id: str = Query(None)):
    query = {}
    if year:
        query["year"] = year
    if status:
        query["status"] = status
    if member_id:
        query["member_id"] = member_id
    forms = await db.annual_forms.find(query, {"_id": 0}).sort("updated_at", -1).to_list(2000)
    settings = await get_settings()
    for f in forms:
        member = await db.members.find_one({"id": f["member_id"]}, {"_id": 0})
        if member:
            ov = await build_year_overview(member, f["year"])
            f["norm_met"] = ov["all_complete"]
            f["achieved_points_live"] = ov["points"]["achieved"]
            f["required_points_live"] = ov["points"]["required"]
            f["achieved_consults_live"] = ov["consults"]["achieved"]
            f["required_consults_live"] = ov["consults"]["required"]
    return forms


@router.get("/admin/{form_id}")
async def admin_detail(form_id: str, admin: dict = Depends(require_admin)):
    form = await db.annual_forms.find_one({"id": form_id}, {"_id": 0})
    if not form:
        raise HTTPException(status_code=404, detail="Jaarformulier niet gevonden")
    member = await db.members.find_one({"id": form["member_id"]}, {"_id": 0})
    ov = await build_year_overview(member, form["year"])
    trainings = await db.training_records.find(
        {"member_id": member["id"], "year": form["year"]}, {"_id": 0}).sort("date", 1).to_list(1000)
    for t in trainings:
        t["documents"] = await db.documents.find(
            {"training_id": t["id"], "is_deleted": False}, {"_id": 0}).to_list(20)
    consult = await db.consult_records.find_one(
        {"member_id": member["id"], "year": form["year"]}, {"_id": 0})
    return {"form": form, "member": member, "overview": ov, "trainings": trainings, "consult": consult}


@router.get("/{form_id}/history")
async def form_history(form_id: str, user: dict = Depends(get_current_user)):
    form = await db.annual_forms.find_one({"id": form_id}, {"_id": 0})
    if not form:
        raise HTTPException(status_code=404, detail="Niet gevonden")
    if user.get("role") != "admin":
        member = await db.members.find_one({"user_id": user["id"]}, {"_id": 0, "id": 1})
        if not member or form["member_id"] != member["id"]:
            raise HTTPException(status_code=403, detail="Geen toegang")
    return await db.status_history.find(
        {"entity_type": "annual_form", "entity_id": form_id}, {"_id": 0}
    ).sort("created_at", 1).to_list(200)


@router.post("/admin/{form_id}/review")
async def review_form(form_id: str, data: AnnualReview, admin: dict = Depends(require_admin)):
    form = await db.annual_forms.find_one({"id": form_id}, {"_id": 0})
    if not form:
        raise HTTPException(status_code=404, detail="Jaarformulier niet gevonden")
    member = await db.members.find_one({"id": form["member_id"]}, {"_id": 0})
    settings = await get_settings()
    templates = settings.get("email_templates", {})
    from_status = form["status"]
    updates = {"reviewed_by": admin["name"], "reviewed_at": now_iso(), "updated_at": now_iso(),
               "admin_remark": data.remark or ""}

    if data.action == "approve":
        ov = await build_year_overview(member, form["year"])
        norms = compute_norms(member.get("license_since", ""), form["year"], settings)
        updates.update({
            "status": "goedgekeurd",
            "applied_points_norm": norms["points_norm"],
            "applied_consults_norm": norms["consults_norm"],
            "achieved_points": ov["points"]["achieved"],
            "achieved_consults": ov["consults"]["achieved"],
        })
        await db.annual_forms.update_one({"id": form_id}, {"$set": updates})
        await add_history("annual_form", form_id, "goedgekeurd", admin, from_status, "goedgekeurd", data.remark)
        if member:
            msg = templates.get("annual_approved", "").format(name=member["name"], year=form["year"])
            await notify(member["user_id"], "annual_approved", "Jaarformulier goedgekeurd", msg,
                         {"form_id": form_id})
    elif data.action == "request_correction":
        updates["status"] = "aanpassing_gevraagd"
        await db.annual_forms.update_one({"id": form_id}, {"$set": updates})
        await add_history("annual_form", form_id, "correctie_gevraagd", admin, from_status,
                          "aanpassing_gevraagd", data.remark)
        if member:
            msg = templates.get("annual_correction", "").format(
                name=member["name"], year=form["year"], remark=data.remark or "")
            await notify(member["user_id"], "annual_correction", "Jaarformulier: correctie gevraagd", msg,
                         {"form_id": form_id})
    elif data.action == "reject":
        updates["status"] = "afgekeurd"
        await db.annual_forms.update_one({"id": form_id}, {"$set": updates})
        await add_history("annual_form", form_id, "afgewezen", admin, from_status, "afgekeurd", data.remark)
        if member:
            await notify(member["user_id"], "annual_rejected", "Jaarformulier afgewezen",
                         f"Uw jaarformulier {form['year']} is afgewezen. {data.remark or ''}",
                         {"form_id": form_id})
    else:
        raise HTTPException(status_code=400, detail="Onbekende actie")
    return await db.annual_forms.find_one({"id": form_id}, {"_id": 0})


@router.post("/admin/{form_id}/generate-pdf")
async def generate_pdf(form_id: str, admin: dict = Depends(require_admin)):
    form = await db.annual_forms.find_one({"id": form_id}, {"_id": 0})
    if not form:
        raise HTTPException(status_code=404, detail="Jaarformulier niet gevonden")
    member = await db.members.find_one({"id": form["member_id"]}, {"_id": 0})
    settings = await get_settings()
    ov = await build_year_overview(member, form["year"])
    norms = compute_norms(member.get("license_since", ""), form["year"], settings)
    trainings = await db.training_records.find(
        {"member_id": member["id"], "year": form["year"]}, {"_id": 0}).sort("date", 1).to_list(1000)
    pdf_bytes = generate_annual_pdf(member, form, trainings, ov, norms)
    path = f"{APP_NAME}/annual_forms/{member['id']}/{form_id}.pdf"
    result = put_object(path, pdf_bytes, "application/pdf")
    doc = {
        "id": str(uuid.uuid4()),
        "member_id": member["id"],
        "training_id": None,
        "annual_form_id": form_id,
        "storage_path": result["path"],
        "original_filename": f"BCND_Jaarformulier_{form['year']}_{member.get('member_number','')}.pdf",
        "content_type": "application/pdf",
        "size": result.get("size", len(pdf_bytes)),
        "doc_type": "jaarformulier_pdf",
        "is_deleted": False,
        "uploaded_by": admin["name"],
        "created_at": now_iso(),
    }
    await db.documents.insert_one(doc)
    await db.annual_forms.update_one({"id": form_id}, {"$set": {"pdf_document_id": doc["id"], "updated_at": now_iso()}})
    await add_history("annual_form", form_id, "pdf_gegenereerd", admin, remark="Definitieve PDF gegenereerd")
    doc.pop("_id", None)
    return doc


@router.get("/{form_id}/pdf")
async def get_pdf(form_id: str, user: dict = Depends(get_current_user)):
    form = await db.annual_forms.find_one({"id": form_id}, {"_id": 0})
    if not form:
        raise HTTPException(status_code=404, detail="Niet gevonden")
    if user.get("role") != "admin":
        member = await db.members.find_one({"user_id": user["id"]}, {"_id": 0, "id": 1})
        if not member or form["member_id"] != member["id"]:
            raise HTTPException(status_code=403, detail="Geen toegang")
    if not form.get("pdf_document_id"):
        raise HTTPException(status_code=404, detail="Nog geen PDF gegenereerd")
    doc = await db.documents.find_one({"id": form["pdf_document_id"]}, {"_id": 0})
    content, ctype = get_object(doc["storage_path"])
    return Response(content=content, media_type="application/pdf",
                    headers={"Content-Disposition": f'inline; filename="{doc["original_filename"]}"'})
