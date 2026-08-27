"""
BCND Jaarformulier - Backend smoke tests
Covers auth (login for admin+member), authorization, listing endpoints.
"""
import os
import pytest
import requests

BASE_URL = os.environ.get("REACT_APP_BACKEND_URL", "https://training-hub-655.preview.emergentagent.com").rstrip("/")
API = f"{BASE_URL}/api"
YEAR = 2026

ADMIN_EMAIL = "bojan.vanderheide@gmail.com"
ADMIN_PASS = "BcndAdmin2026!"
MEMBER_EMAIL = "lid@bcnd-demo.nl"
MEMBER_PASS = "Lid2026!"


def _login(email, password):
    s = requests.Session()
    r = s.post(f"{API}/auth/login", json={"email": email, "password": password}, timeout=15)
    return s, r


@pytest.fixture(scope="module")
def admin_session():
    s, r = _login(ADMIN_EMAIL, ADMIN_PASS)
    assert r.status_code == 200, f"admin login failed: {r.status_code} {r.text}"
    return s


@pytest.fixture(scope="module")
def member_session():
    s, r = _login(MEMBER_EMAIL, MEMBER_PASS)
    assert r.status_code == 200, f"member login failed: {r.status_code} {r.text}"
    return s


# ---------- Health ----------
def test_api_root():
    r = requests.get(f"{API}/", timeout=10)
    assert r.status_code == 200


# ---------- Auth ----------
def test_admin_login():
    s, r = _login(ADMIN_EMAIL, ADMIN_PASS)
    assert r.status_code == 200
    data = r.json()
    assert data.get("role") == "admin"
    assert data.get("email") == ADMIN_EMAIL
    # httpOnly cookies present
    cookie_names = {c.name for c in s.cookies}
    assert "access_token" in cookie_names, f"expected access_token cookie, got {cookie_names}"


def test_member_login():
    s, r = _login(MEMBER_EMAIL, MEMBER_PASS)
    assert r.status_code == 200
    data = r.json()
    assert data.get("role") == "member"
    assert data.get("member_id")


def test_login_invalid():
    r = requests.post(f"{API}/auth/login", json={"email": ADMIN_EMAIL, "password": "wrong"}, timeout=10)
    assert r.status_code == 401


def test_auth_me_admin(admin_session):
    r = admin_session.get(f"{API}/auth/me", timeout=10)
    assert r.status_code == 200
    assert r.json().get("email") == ADMIN_EMAIL


def test_auth_me_member(member_session):
    r = member_session.get(f"{API}/auth/me", timeout=10)
    assert r.status_code == 200
    assert r.json().get("email") == MEMBER_EMAIL


# ---------- Authorization (member cannot access admin) ----------
@pytest.mark.parametrize("path", [
    "/admin/dashboard",
    "/admin/review-queue",
    "/annual-forms/admin/list",
    "/members",
    "/settings",
])
def test_member_cannot_access_admin(member_session, path):
    r = member_session.get(f"{API}{path}", timeout=10)
    assert r.status_code in (401, 403), f"{path} returned {r.status_code} for member"


# ---------- Member endpoints ----------
def test_member_trainings_list(member_session):
    r = member_session.get(f"{API}/trainings", timeout=10)
    assert r.status_code == 200
    assert isinstance(r.json(), list)


def test_member_overview(member_session):
    r = member_session.get(f"{API}/annual-forms/overview", params={"year": YEAR}, timeout=15)
    assert r.status_code == 200, r.text
    data = r.json()
    assert "achieved_points" in data or "points" in data or isinstance(data, dict)


def test_member_consults_get(member_session):
    r = member_session.get(f"{API}/consults", params={"year": YEAR}, timeout=10)
    assert r.status_code == 200
    d = r.json()
    assert "total_consults" in d


def test_member_annual_form_get(member_session):
    r = member_session.get(f"{API}/annual-forms", params={"year": YEAR}, timeout=10)
    assert r.status_code == 200


def test_notifications_list(member_session):
    r = member_session.get(f"{API}/notifications", timeout=10)
    assert r.status_code == 200
    d = r.json()
    # can be either list or dict with items
    assert isinstance(d, (list, dict))
    if isinstance(d, dict):
        assert "items" in d


def test_documents_list(member_session):
    r = member_session.get(f"{API}/documents", timeout=10)
    assert r.status_code == 200


# ---------- Admin endpoints ----------
def test_admin_dashboard(admin_session):
    r = admin_session.get(f"{API}/admin/dashboard", timeout=15)
    assert r.status_code == 200, r.text


def test_admin_review_queue(admin_session):
    r = admin_session.get(f"{API}/admin/review-queue", timeout=15)
    assert r.status_code == 200


def test_admin_members_list(admin_session):
    r = admin_session.get(f"{API}/members", timeout=10)
    assert r.status_code == 200
    assert isinstance(r.json(), list)


def test_admin_annual_forms_list(admin_session):
    r = admin_session.get(f"{API}/annual-forms/admin/list", timeout=10)
    assert r.status_code == 200


def test_admin_settings_get(admin_session):
    r = admin_session.get(f"{API}/settings", timeout=10)
    assert r.status_code == 200
    d = r.json()
    assert isinstance(d, dict)


def test_settings_public(member_session):
    # Endpoint requires auth but returns non-admin fields
    r = member_session.get(f"{API}/settings/public", timeout=10)
    assert r.status_code == 200
    assert "points_norm" in r.json()


# ---------- Logout ----------
def test_logout():
    s, r = _login(MEMBER_EMAIL, MEMBER_PASS)
    assert r.status_code == 200
    r2 = s.post(f"{API}/auth/logout", timeout=10)
    assert r2.status_code == 200
