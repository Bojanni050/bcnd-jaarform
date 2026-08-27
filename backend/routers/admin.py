from datetime import datetime, timezone

from fastapi import APIRouter, Depends, Query

from database import db
from auth import require_admin
from helpers import get_settings
from business import build_year_overview, days_until_deadline

router = APIRouter(prefix="/admin", tags=["admin"])


@router.get("/dashboard")
async def dashboard(admin: dict = Depends(require_admin), year: int = Query(None)):
    yr = year or datetime.now(timezone.utc).year
    settings = await get_settings()

    trainings_pending = await db.training_records.count_documents({"status": "ingediend"})
    trainings_in_review = await db.training_records.count_documents({"status": "in_beoordeling"})
    trainings_changes = await db.training_records.count_documents({"status": "aanpassing_gevraagd"})
    forms_to_review = await db.annual_forms.count_documents({"status": {"$in": ["ingediend", "in_beoordeling"]}})
    forms_approved = await db.annual_forms.count_documents({"status": "goedgekeurd", "year": yr})

    members = await db.members.find({"status": "active"}, {"_id": 0}).to_list(2000)
    behind = []
    deadline_soon = []
    missing_docs = []
    days = days_until_deadline(settings, yr)
    for m in members:
        ov = await build_year_overview(m, yr)
        if not ov["all_complete"]:
            behind.append({"member_id": m["id"], "name": m["name"],
                           "points": ov["points"], "consults": ov["consults"]})
            form = await db.annual_forms.find_one({"member_id": m["id"], "year": yr}, {"_id": 0, "status": 1})
            submitted = form and form.get("status") in ("ingediend", "in_beoordeling", "goedgekeurd")
            if 0 <= days <= 45 and not submitted:
                deadline_soon.append({"member_id": m["id"], "name": m["name"], "days": days})
        if ov["counts"]["missing_documents"] > 0:
            missing_docs.append({"member_id": m["id"], "name": m["name"],
                                 "count": ov["counts"]["missing_documents"]})

    return {
        "year": yr,
        "days_until_deadline": days,
        "trainings_pending": trainings_pending,
        "trainings_in_review": trainings_in_review,
        "trainings_changes": trainings_changes,
        "forms_to_review": forms_to_review,
        "forms_approved": forms_approved,
        "members_total": len(members),
        "members_behind": behind,
        "members_deadline_soon": deadline_soon,
        "members_missing_docs": missing_docs,
    }


@router.get("/review-queue")
async def review_queue(admin: dict = Depends(require_admin)):
    trainings = await db.training_records.find(
        {"status": {"$in": ["ingediend", "in_beoordeling"]}}, {"_id": 0}
    ).sort("submitted_at", 1).to_list(500)
    return trainings
