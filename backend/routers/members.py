import uuid
from datetime import datetime, timezone

from bson import ObjectId
from fastapi import APIRouter, HTTPException, Depends

from database import db
from models import MemberCreate, MemberUpdateSelf, MemberUpdateAdmin
from auth import get_current_user, require_admin, get_current_member, hash_password
from helpers import now_iso

router = APIRouter(prefix="/members", tags=["members"])


@router.get("/me")
async def get_me(member: dict = Depends(get_current_member)):
    return member


@router.put("/me")
async def update_me(data: MemberUpdateSelf, member: dict = Depends(get_current_member)):
    updates = {k: v for k, v in data.model_dump().items() if v is not None}
    if updates:
        updates["updated_at"] = now_iso()
        await db.members.update_one({"id": member["id"]}, {"$set": updates})
    return await db.members.find_one({"id": member["id"]}, {"_id": 0})


@router.get("")
async def list_members(admin: dict = Depends(require_admin), q: str = "", status: str = ""):
    query = {}
    if status:
        query["status"] = status
    if q:
        query["$or"] = [
            {"name": {"$regex": q, "$options": "i"}},
            {"email": {"$regex": q, "$options": "i"}},
            {"member_number": {"$regex": q, "$options": "i"}},
        ]
    members = await db.members.find(query, {"_id": 0}).sort("name", 1).to_list(1000)
    return members


@router.post("")
async def create_member(data: MemberCreate, admin: dict = Depends(require_admin)):
    email = data.email.lower()
    if await db.users.find_one({"email": email}):
        raise HTTPException(status_code=400, detail="E-mailadres is al in gebruik")
    user_doc = {
        "email": email,
        "password_hash": hash_password(data.password),
        "name": data.name,
        "role": "member",
        "created_at": now_iso(),
    }
    res = await db.users.insert_one(user_doc)
    uid = str(res.inserted_id)
    member = {
        "id": str(uuid.uuid4()),
        "user_id": uid,
        "name": data.name,
        "email": email,
        "address": data.address,
        "city": data.city,
        "postal_code": data.postal_code,
        "member_number": data.member_number,
        "license_since": data.license_since,
        "phone": data.phone,
        "status": data.status,
        "notes": "",
        "created_at": now_iso(),
    }
    await db.members.insert_one(member)
    member.pop("_id", None)
    return member


@router.get("/{member_id}")
async def get_member(member_id: str, admin: dict = Depends(require_admin)):
    member = await db.members.find_one({"id": member_id}, {"_id": 0})
    if not member:
        raise HTTPException(status_code=404, detail="Lid niet gevonden")
    return member


@router.put("/{member_id}")
async def update_member(member_id: str, data: MemberUpdateAdmin, admin: dict = Depends(require_admin)):
    updates = {k: v for k, v in data.model_dump().items() if v is not None}
    if not updates:
        raise HTTPException(status_code=400, detail="Geen wijzigingen")
    updates["updated_at"] = now_iso()
    r = await db.members.update_one({"id": member_id}, {"$set": updates})
    if r.matched_count == 0:
        raise HTTPException(status_code=404, detail="Lid niet gevonden")
    return await db.members.find_one({"id": member_id}, {"_id": 0})
