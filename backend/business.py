from datetime import datetime, timezone, date

from database import db
from helpers import get_settings


def membership_year(license_since_iso: str, form_year: int) -> int:
    try:
        ly = int(str(license_since_iso)[:4])
    except Exception:
        ly = form_year
    return max(1, form_year - ly + 1)


def compute_norms(license_since_iso: str, form_year: int, settings: dict) -> dict:
    my = membership_year(license_since_iso, form_year)
    consults_norms = settings.get("consults_norms", {"1": 10, "2": 20, "3": 30, "4": 40})
    consults_norm = int(consults_norms.get(str(min(my, 4)), 40))
    # In the registration year there is no minimum bij-/nascholingsnorm.
    points_norm = 0 if my == 1 else int(settings.get("points_norm", 8))
    return {"membership_year": my, "points_norm": points_norm, "consults_norm": consults_norm}


def days_until_deadline(settings: dict, year: int) -> int:
    day = int(settings.get("deadline_day", 31))
    month = int(settings.get("deadline_month", 12))
    deadline = date(year, month, day)
    today = datetime.now(timezone.utc).date()
    return (deadline - today).days


def requires_own_certificate(activity_type: str) -> bool:
    # BCND-organised activities: attendance lists are supplied directly to admin.
    return activity_type in ("externe_bijscholing", "overige_activiteit")


async def build_year_overview(member: dict, year: int) -> dict:
    settings = await get_settings()
    norms = compute_norms(member.get("license_since", f"{year}-01-01"), year, settings)

    trainings = await db.training_records.find(
        {"member_id": member["id"], "year": year}, {"_id": 0}
    ).to_list(1000)

    approved = [t for t in trainings if t.get("status") == "goedgekeurd"]
    in_review = [t for t in trainings if t.get("status") in ("ingediend", "in_beoordeling")]
    changes = [t for t in trainings if t.get("status") == "aanpassing_gevraagd"]
    achieved_points = sum(float(t.get("points") or 0) for t in approved)

    # Missing documents: own-certificate-required trainings without a document.
    doc_training_ids = set()
    docs = await db.documents.find(
        {"member_id": member["id"], "is_deleted": False, "training_id": {"$ne": None}},
        {"_id": 0, "training_id": 1},
    ).to_list(2000)
    for d in docs:
        doc_training_ids.add(d["training_id"])
    missing_docs = [
        t for t in trainings
        if requires_own_certificate(t.get("activity_type", "")) and t["id"] not in doc_training_ids
        and t.get("status") != "afgekeurd"
    ]

    consult = await db.consult_records.find_one({"member_id": member["id"], "year": year}, {"_id": 0})
    total_consults = int(consult.get("total_consults", 0)) if consult else 0

    points_norm = norms["points_norm"]
    consults_norm = norms["consults_norm"]

    points_complete = points_norm == 0 or achieved_points >= points_norm
    consults_complete = total_consults >= consults_norm

    return {
        "year": year,
        "membership_year": norms["membership_year"],
        "points": {
            "achieved": achieved_points,
            "required": points_norm,
            "remaining": max(0, points_norm - achieved_points),
            "percentage": 100 if points_norm == 0 else min(100, round(achieved_points / points_norm * 100)),
            "complete": points_complete,
        },
        "consults": {
            "achieved": total_consults,
            "required": consults_norm,
            "remaining": max(0, consults_norm - total_consults),
            "percentage": min(100, round(total_consults / consults_norm * 100)) if consults_norm else 100,
            "complete": consults_complete,
            "first_consults": int(consult.get("first_consults", 0)) if consult else 0,
            "followup_consults": int(consult.get("followup_consults", 0)) if consult else 0,
            "other_activities": consult.get("other_activities", "") if consult else "",
        },
        "counts": {
            "trainings_total": len(trainings),
            "approved": len(approved),
            "in_review": len(in_review),
            "changes_requested": len(changes),
            "missing_documents": len(missing_docs),
        },
        "all_complete": points_complete and consults_complete,
        "days_until_deadline": days_until_deadline(settings, year),
    }
