from pydantic import BaseModel, EmailStr, Field
from typing import Optional


# ---------- Auth ----------
class RegisterInput(BaseModel):
    email: EmailStr
    password: str
    name: str


class LoginInput(BaseModel):
    email: EmailStr
    password: str


# ---------- Members ----------
class MemberCreate(BaseModel):
    name: str
    email: EmailStr
    password: str
    address: Optional[str] = ""
    city: Optional[str] = ""
    postal_code: Optional[str] = ""
    member_number: Optional[str] = ""
    license_since: str  # ISO date (YYYY-MM-DD)
    phone: Optional[str] = ""
    status: str = "active"


class MemberUpdateSelf(BaseModel):
    address: Optional[str] = None
    city: Optional[str] = None
    postal_code: Optional[str] = None
    phone: Optional[str] = None


class MemberUpdateAdmin(BaseModel):
    name: Optional[str] = None
    address: Optional[str] = None
    city: Optional[str] = None
    postal_code: Optional[str] = None
    member_number: Optional[str] = None
    license_since: Optional[str] = None
    phone: Optional[str] = None
    status: Optional[str] = None
    notes: Optional[str] = None


# ---------- Trainings ----------
class TrainingCreate(BaseModel):
    date: str
    hours: float = 0
    organization: str = ""
    subject: str = ""
    content_explanation: str = ""
    speaker: str = ""
    activity_type: str = "externe_bijscholing"
    member_remarks: Optional[str] = ""
    year: Optional[int] = None
    status: str = "ingediend"  # concept or ingediend


class TrainingUpdate(BaseModel):
    date: Optional[str] = None
    hours: Optional[float] = None
    organization: Optional[str] = None
    subject: Optional[str] = None
    content_explanation: Optional[str] = None
    speaker: Optional[str] = None
    activity_type: Optional[str] = None
    member_remarks: Optional[str] = None


class TrainingReview(BaseModel):
    action: str  # approve | reject | request_changes | assign_points
    points: Optional[float] = None
    remark: Optional[str] = ""


# ---------- Consults ----------
class ConsultUpsert(BaseModel):
    year: int
    total_consults: int = 0
    first_consults: int = 0
    followup_consults: int = 0
    other_activities: Optional[str] = ""


# ---------- Annual forms ----------
class AnnualSubmit(BaseModel):
    deviation_reason: Optional[str] = ""


class AnnualReview(BaseModel):
    action: str  # approve | request_correction | reject
    remark: Optional[str] = ""


# ---------- Settings ----------
class SettingsUpdate(BaseModel):
    points_norm: Optional[int] = None
    consults_norms: Optional[dict] = None
    deadline_day: Optional[int] = None
    deadline_month: Optional[int] = None
    email_templates: Optional[dict] = None
    notifications_enabled: Optional[bool] = None
