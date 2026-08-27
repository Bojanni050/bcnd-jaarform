import uuid
from datetime import datetime, timezone

from fastapi import APIRouter, HTTPException, Depends, UploadFile, File, Form, Header, Query, Request
from fastapi.responses import Response

from database import db
from auth import get_current_user, get_jwt_secret
from storage import put_object, get_object, APP_NAME, MIME_TYPES
from helpers import now_iso
import jwt
from bson import ObjectId

router = APIRouter(prefix="/documents", tags=["documents"])

ALLOWED = {"pdf", "png", "jpg", "jpeg", "gif", "webp"}


async def _member_of(user):
    return await db.members.find_one({"user_id": user["id"]}, {"_id": 0})


@router.post("/upload")
async def upload_document(file: UploadFile = File(...), training_id: str = Form(None),
                          doc_type: str = Form("deelnamebewijs"),
                          user: dict = Depends(get_current_user)):
    ext = (file.filename.rsplit(".", 1)[-1] if "." in file.filename else "bin").lower()
    if ext not in ALLOWED:
        raise HTTPException(status_code=400, detail="Bestandstype niet toegestaan (alleen PDF en afbeeldingen)")

    if user.get("role") == "admin":
        # admin uploading on behalf: need member via training
        member_id = None
        if training_id:
            tr = await db.training_records.find_one({"id": training_id}, {"_id": 0, "member_id": 1})
            member_id = tr["member_id"] if tr else None
    else:
        member = await _member_of(user)
        if not member:
            raise HTTPException(status_code=404, detail="Geen lidprofiel")
        member_id = member["id"]
        if training_id:
            tr = await db.training_records.find_one({"id": training_id}, {"_id": 0})
            if not tr or tr["member_id"] != member_id:
                raise HTTPException(status_code=403, detail="Geen toegang tot deze bijscholing")

    data = await file.read()
    if len(data) > 15 * 1024 * 1024:
        raise HTTPException(status_code=400, detail="Bestand te groot (max 15MB)")
    path = f"{APP_NAME}/uploads/{member_id}/{uuid.uuid4()}.{ext}"
    content_type = file.content_type or MIME_TYPES.get(ext, "application/octet-stream")
    result = put_object(path, data, content_type)
    doc = {
        "id": str(uuid.uuid4()),
        "member_id": member_id,
        "training_id": training_id,
        "annual_form_id": None,
        "storage_path": result["path"],
        "original_filename": file.filename,
        "content_type": content_type,
        "size": result.get("size", len(data)),
        "doc_type": doc_type,
        "is_deleted": False,
        "uploaded_by": user["name"],
        "created_at": now_iso(),
    }
    await db.documents.insert_one(doc)
    doc.pop("_id", None)
    return doc


@router.get("")
async def list_documents(training_id: str = Query(None), member_id: str = Query(None),
                         user: dict = Depends(get_current_user)):
    query = {"is_deleted": False}
    if user.get("role") != "admin":
        member = await _member_of(user)
        if not member:
            return []
        query["member_id"] = member["id"]
    elif member_id:
        query["member_id"] = member_id
    if training_id:
        query["training_id"] = training_id
    return await db.documents.find(query, {"_id": 0}).sort("created_at", -1).to_list(500)


@router.delete("/{doc_id}")
async def delete_document(doc_id: str, user: dict = Depends(get_current_user)):
    doc = await db.documents.find_one({"id": doc_id}, {"_id": 0})
    if not doc:
        raise HTTPException(status_code=404, detail="Document niet gevonden")
    if user.get("role") != "admin":
        member = await _member_of(user)
        if not member or doc["member_id"] != member["id"]:
            raise HTTPException(status_code=403, detail="Geen toegang")
    await db.documents.update_one({"id": doc_id}, {"$set": {"is_deleted": True}})
    return {"ok": True}


@router.get("/{doc_id}/download")
async def download_document(doc_id: str, request: Request, authorization: str = Header(None),
                           auth: str = Query(None)):
    token = None
    if authorization and authorization.startswith("Bearer "):
        token = authorization[7:]
    elif auth:
        token = auth
    else:
        token = request.cookies.get("access_token")
    if not token:
        raise HTTPException(status_code=401, detail="Niet geautoriseerd")
    try:
        payload = jwt.decode(token, get_jwt_secret(), algorithms=["HS256"])
        u = await db.users.find_one({"_id": ObjectId(payload["sub"])})
    except Exception:
        raise HTTPException(status_code=401, detail="Ongeldig token")
    if not u:
        raise HTTPException(status_code=401, detail="Gebruiker niet gevonden")

    doc = await db.documents.find_one({"id": doc_id, "is_deleted": False}, {"_id": 0})
    if not doc:
        raise HTTPException(status_code=404, detail="Document niet gevonden")
    if u.get("role") != "admin":
        member = await db.members.find_one({"user_id": str(u["_id"])}, {"_id": 0, "id": 1})
        if not member or doc["member_id"] != member["id"]:
            raise HTTPException(status_code=403, detail="Geen toegang")
    content, ctype = get_object(doc["storage_path"])
    return Response(content=content, media_type=doc.get("content_type", ctype),
                    headers={"Content-Disposition": f'inline; filename="{doc["original_filename"]}"'})
