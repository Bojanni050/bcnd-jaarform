# BCND Auth Testing

## Credentials
- Admin: bojan.vanderheide@gmail.com / BcndAdmin2026! (role admin)
- Member (demo): lid@bcnd-demo.nl / Lid2026! (role member, Marloes de Vries, member_number BCND-2023-045, license_since 2023-03-01)

## Auth model
- Cookie-based httpOnly JWT (access_token/refresh_token, SameSite=None, Secure). Frontend uses axios withCredentials. Bearer header also accepted.
- Endpoints: POST /api/auth/login, /register, /logout, /refresh; GET /api/auth/me

## Quick check
curl -c c.txt -X POST http://localhost:8001/api/auth/login -H "Content-Type: application/json" -d '{"email":"bojan.vanderheide@gmail.com","password":"BcndAdmin2026!"}'
curl -b c.txt http://localhost:8001/api/auth/me
