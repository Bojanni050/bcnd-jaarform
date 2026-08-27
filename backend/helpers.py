import uuid
from datetime import datetime, timezone

from database import db

DEFAULT_SETTINGS = {
    "id": "global",
    "points_norm": 8,
    "consults_norms": {"1": 10, "2": 20, "3": 30, "4": 40},
    "deadline_day": 31,
    "deadline_month": 12,
    "notifications_enabled": True,
    "email_templates": {
        "training_submitted": "Beste {name}, uw bijscholing '{subject}' is ontvangen en wordt beoordeeld.",
        "training_approved": "Beste {name}, uw bijscholing '{subject}' is goedgekeurd met {points} punt(en).",
        "training_rejected": "Beste {name}, uw bijscholing '{subject}' is afgekeurd. {remark}",
        "training_changes": "Beste {name}, voor uw bijscholing '{subject}' is aanvullende informatie gevraagd: {remark}",
        "annual_submitted": "Beste {name}, uw jaarformulier {year} is ingediend en wacht op beoordeling.",
        "annual_approved": "Beste {name}, uw jaarformulier {year} is goedgekeurd.",
        "annual_correction": "Beste {name}, uw jaarformulier {year} is teruggestuurd voor correctie: {remark}",
        "deadline_reminder": "Beste {name}, u heeft nog {days} dagen om uw jaarformulier {year} in te dienen.",
    },
}


def now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


async def get_settings() -> dict:
    s = await db.settings.find_one({"id": "global"}, {"_id": 0})
    if not s:
        await db.settings.insert_one(dict(DEFAULT_SETTINGS))
        return dict(DEFAULT_SETTINGS)
    return s


async def add_history(entity_type, entity_id, action, actor, from_status=None, to_status=None, remark=""):
    doc = {
        "id": str(uuid.uuid4()),
        "entity_type": entity_type,
        "entity_id": entity_id,
        "action": action,
        "from_status": from_status,
        "to_status": to_status,
        "remark": remark or "",
        "actor_id": actor.get("id"),
        "actor_name": actor.get("name"),
        "actor_role": actor.get("role"),
        "created_at": now_iso(),
    }
    await db.status_history.insert_one(doc)


async def notify(user_id, ntype, title, message, related=None):
    await db.notifications.insert_one({
        "id": str(uuid.uuid4()),
        "user_id": user_id,
        "type": ntype,
        "title": title,
        "message": message,
        "related": related or {},
        "read": False,
        "created_at": now_iso(),
    })


async def notify_admins(ntype, title, message, related=None):
    admins = await db.users.find({"role": "admin"}, {"_id": 1}).to_list(100)
    for a in admins:
        await notify(str(a["_id"]), ntype, title, message, related)
