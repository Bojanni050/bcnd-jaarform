from fastapi import APIRouter, Depends

from database import db
from models import SettingsUpdate
from auth import require_admin, get_current_user
from helpers import get_settings, now_iso

router = APIRouter(prefix="/settings", tags=["settings"])


@router.get("")
async def read_settings(admin: dict = Depends(require_admin)):
    return await get_settings()


@router.get("/public")
async def public_settings(user: dict = Depends(get_current_user)):
    s = await get_settings()
    return {
        "points_norm": s.get("points_norm"),
        "consults_norms": s.get("consults_norms"),
        "deadline_day": s.get("deadline_day"),
        "deadline_month": s.get("deadline_month"),
    }


@router.put("")
async def update_settings(data: SettingsUpdate, admin: dict = Depends(require_admin)):
    await get_settings()
    updates = {k: v for k, v in data.model_dump().items() if v is not None}
    updates["updated_at"] = now_iso()
    await db.settings.update_one({"id": "global"}, {"$set": updates})
    return await get_settings()
