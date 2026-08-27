import uuid
from datetime import datetime, timezone

from fastapi import APIRouter, HTTPException, Depends, Query

from database import db
from models import ConsultUpsert
from auth import get_current_user, get_current_member, require_admin
from helpers import now_iso, add_history

router = APIRouter(prefix="/consults", tags=["consults"])


async def _resolve_member(user, member_id):
    if user.get("role") == "admin":
        if not member_id:
            raise HTTPException(status_code=400, detail="member_id vereist")
        m = await db.members.find_one({"id": member_id}, {"_id": 0})
        if not m:
            raise HTTPException(status_code=404, detail="Lid niet gevonden")
        return m
    m = await db.members.find_one({"user_id": user["id"]}, {"_id": 0})
    if not m:
        raise HTTPException(status_code=404, detail="Geen lidprofiel")
    return m


@router.get("")
async def get_consult(year: int = Query(...), member_id: str = Query(None),
                      user: dict = Depends(get_current_user)):
    member = await _resolve_member(user, member_id)
    rec = await db.consult_records.find_one({"member_id": member["id"], "year": year}, {"_id": 0})
    if not rec:
        return {"member_id": member["id"], "year": year, "total_consults": 0,
                "first_consults": 0, "followup_consults": 0, "other_activities": ""}
    return rec


@router.put("")
async def upsert_consult(data: ConsultUpsert, member_id: str = Query(None),
                         user: dict = Depends(get_current_user)):
    member = await _resolve_member(user, member_id)
    total = data.total_consults
    if not total and (data.first_consults or data.followup_consults):
        total = data.first_consults + data.followup_consults
    doc = {
        "member_id": member["id"],
        "year": data.year,
        "total_consults": total,
        "first_consults": data.first_consults,
        "followup_consults": data.followup_consults,
        "other_activities": data.other_activities,
        "updated_at": now_iso(),
    }
    existing = await db.consult_records.find_one({"member_id": member["id"], "year": data.year})
    if existing:
        await db.consult_records.update_one(
            {"member_id": member["id"], "year": data.year}, {"$set": doc})
    else:
        doc["id"] = str(uuid.uuid4())
        doc["created_at"] = now_iso()
        await db.consult_records.insert_one(doc)
    await add_history("consult", f"{member['id']}:{data.year}", "consulten_bijgewerkt", user,
                      remark=f"Totaal {total} consulten voor {data.year}")
    return await db.consult_records.find_one({"member_id": member["id"], "year": data.year}, {"_id": 0})
