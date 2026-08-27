from dotenv import load_dotenv
from pathlib import Path

ROOT_DIR = Path(__file__).parent
load_dotenv(ROOT_DIR / ".env")

import os
import uuid
import logging
from datetime import datetime, timezone

from fastapi import FastAPI, APIRouter
from starlette.middleware.cors import CORSMiddleware

from database import db, client
from auth import hash_password, verify_password
from helpers import get_settings
from storage import init_storage

from routers import (
    auth_routes, members, trainings, consults, annual_forms,
    documents, admin, settings_routes, notifications,
)

logging.basicConfig(level=logging.INFO,
                    format="%(asctime)s - %(name)s - %(levelname)s - %(message)s")
logger = logging.getLogger("bcnd")

app = FastAPI(title="BCND Nascholingsadministratie")

api_router = APIRouter(prefix="/api")


@api_router.get("/")
async def root():
    return {"message": "BCND Nascholingsadministratie API"}


api_router.include_router(auth_routes.router)
api_router.include_router(members.router)
api_router.include_router(trainings.router)
api_router.include_router(consults.router)
api_router.include_router(annual_forms.router)
api_router.include_router(documents.router)
api_router.include_router(admin.router)
api_router.include_router(settings_routes.router)
api_router.include_router(notifications.router)

app.include_router(api_router)

_frontend = os.environ.get("FRONTEND_URL", "http://localhost:3000")
app.add_middleware(
    CORSMiddleware,
    allow_origins=[_frontend, "http://localhost:3000"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


async def seed_admin():
    admin_email = os.environ.get("ADMIN_EMAIL", "admin@example.com").lower()
    admin_password = os.environ.get("ADMIN_PASSWORD", "admin123")
    existing = await db.users.find_one({"email": admin_email})
    if existing is None:
        await db.users.insert_one({
            "email": admin_email,
            "password_hash": hash_password(admin_password),
            "name": "BCND Administratie",
            "role": "admin",
            "created_at": datetime.now(timezone.utc).isoformat(),
        })
        logger.info("Admin seeded: %s", admin_email)
    elif not verify_password(admin_password, existing["password_hash"]):
        await db.users.update_one({"email": admin_email},
                                  {"$set": {"password_hash": hash_password(admin_password)}})


async def seed_demo_member():
    email = "lid@bcnd-demo.nl"
    existing = await db.users.find_one({"email": email})
    if existing:
        return
    res = await db.users.insert_one({
        "email": email,
        "password_hash": hash_password("Lid2026!"),
        "name": "Marloes de Vries",
        "role": "member",
        "created_at": datetime.now(timezone.utc).isoformat(),
    })
    uid = str(res.inserted_id)
    await db.members.insert_one({
        "id": str(uuid.uuid4()),
        "user_id": uid,
        "name": "Marloes de Vries",
        "email": email,
        "address": "Dorpsstraat 12",
        "city": "Utrecht",
        "postal_code": "3511 AB",
        "member_number": "BCND-2023-045",
        "license_since": "2023-03-01",
        "phone": "06-12345678",
        "status": "active",
        "notes": "",
        "created_at": datetime.now(timezone.utc).isoformat(),
    })
    logger.info("Demo member seeded: %s", email)


@app.on_event("startup")
async def startup():
    await db.users.create_index("email", unique=True)
    await db.members.create_index("user_id")
    await db.members.create_index("id", unique=True)
    await db.training_records.create_index([("member_id", 1), ("year", 1)])
    await db.consult_records.create_index([("member_id", 1), ("year", 1)], unique=True)
    await db.annual_forms.create_index([("member_id", 1), ("year", 1)], unique=True)
    await db.status_history.create_index([("entity_type", 1), ("entity_id", 1)])
    await db.notifications.create_index("user_id")
    await seed_admin()
    await seed_demo_member()
    await get_settings()
    try:
        init_storage()
        logger.info("Storage initialised")
    except Exception as e:
        logger.error("Storage init failed: %s", e)


@app.on_event("shutdown")
async def shutdown():
    client.close()
