# BCND Jaarformulier & Nascholingsadministratie — PRD

## Original problem statement
Complete webapplicatie voor de BCND (Beroepsvereniging voor Complementaire en Natuurlijke geneeswijzen voor Dieren) voor het digitaal registreren, beoordelen en verwerken van bij- en nascholingen, consulten en jaarlijkse jaarformulieren van licentieleden. Referentie: BCND_Jaarformulier_2023.pdf. Doel: administratie automatiseren en het jaarformulier automatisch samenstellen uit de database.

## User choices
- Auth: JWT e-mail/wachtwoord (httpOnly cookies)
- Notificaties: in-app (inbox/badge); e-mailteksten wel configureerbaar in settings
- PDF: server-side gegenereerd + opgeslagen (reportlab + object storage)
- Taal: Nederlands (Engelse code/labels)

## Architecture
- Backend: FastAPI (modulair) + MongoDB (motor). Routers: auth, members, trainings, consults, annual_forms, documents, admin, settings, notifications. Business logic in business.py (compute_norms, build_year_overview), pdf_generator.py, helpers.py (audit + notify + settings).
- Frontend: React + react-router + shadcn/ui + Tailwind (Forest Green/Sage/Terracotta thema, Work Sans/IBM Plex Sans). AuthContext, AppLayout met sidebar + notificaties.
- Storage: Emergent object storage (deelnamebewijzen + jaarformulier-PDF's).
- Collections: users, members, training_records, consult_records, annual_forms, documents, status_history, notifications, settings.

## User personas
- Licentielid: registreert bijscholingen/consulten, uploadt bewijzen, dient jaarformulier in, ziet voortgang.
- BCND administratie: beoordeelt bijscholingen (punten), beheert leden/instellingen, beoordeelt jaarformulieren, genereert definitieve PDF.

## Core requirements (static)
- Rollen + strikte autorisatie (lid ziet alleen eigen data; server-side checks).
- Bijscholing-statussen: concept, ingediend, in_beoordeling, aanpassing_gevraagd, goedgekeurd, afgekeurd.
- Punten tellen pas na goedkeuring. Norm 8 pt/jaar (0 in aanmeldjaar), configureerbaar.
- Consultennorm per lidmaatschapsjaar (10/20/30/40), configureerbaar.
- Type activiteit (externe/BCND-bijscholing/ledenbijeenkomst/overige); BCND-activiteiten vereisen geen eigen bewijs.
- Automatisch jaaroverzicht + jaarformulier; indienen met verplichte toelichting bij niet-behaalde norm; lock na indienen; correctieronde.
- Audit trail (status_history) op elke wijziging met actor + tijd.
- Server-side PDF conform BCND-formulier; opgeslagen bij jaarformulier.
- Deadline 31-12 (configureerbaar) + dagen-teller.

## Implemented (2026-08-27)
- ✅ Fase 1: DB-model, JWT-auth, rollen, leden(beheer), admin dashboard, basis-UI.
- ✅ Fase 2: bijscholingen registreren/concept/indienen, document-upload, statussen, admin-beoordeling (punten/goedkeuren/afkeuren/aanpassing), audit trail + historie-timeline.
- ✅ Fase 3: consulten registratie, automatische totalen, normberekening op lidmaatschapsjaar.
- ✅ Fase 4: automatisch jaaroverzicht, normcontrole, indienen (lock), admin-beoordeling + correctieronde.
- ✅ Fase 5: server-side PDF-generatie + opslag; in-app notificaties; configureerbare e-mailteksten & deadline in settings.
- ✅ Fase 6 (deels): responsive dashboards, autorisatie geverifieerd, progress rings, document-preview.
- ✅ Getest: backend 24/24 pytest, frontend e2e 100% (testing agent iteration_1).

## Backlog / remaining
- P1: Echte e-mailverzending (Resend) i.p.v. alleen in-app (gebruiker koos later).
- P1: Automatische deadline-herinneringen (scheduled) — nu on-demand via dashboard.
- P2: shadcn DatePicker i.p.v. native date input.
- P2: Uitgebreidere admin-rapportages/export (CSV).
- P2: WordPress/API-koppeling (lid.bcnd.eu) — kern is API-first, klaar voor uitbreiding.
- P2: /settings/public echt publiek maken of hernoemen.

## WordPress plugin migration (2026-08-27)
- ✅ Volledige zelfstandige WordPress-plugin gebouwd: /app/wp-plugin/bcnd-jaarformulier/ → ZIP: /app/bcnd-jaarformulier.zip
- Backend volledig in PHP (WP is source of truth): eigen tabellen wp_bcnd_* via dbDelta, rollen/capabilities (bcnd_admin/bcnd_member + caps), namespaced REST API /wp-json/bcnd/v1/ met server-side autorisatie + member-isolatie, privé documentopslag (uploads/bcnd-private + .htaccess deny), zelfstandige PDF-generator (Helvetica, geen externe libs), notificaties via wp_mail + in-app, WP-cron deadline-herinneringen, audit trail met old/new value.
- Frontend: bestaande React-UI hergebruikt, WP-aware gemaakt (window.BCND boot, HashRouter, X-WP-Nonce, WP-login), gecompileerd naar assets/app/ (geen npm nodig in productie). Shortcode [bcnd_portal] + WP-adminmenu BCND met secties.
- Geverifieerd in dev-omgeving: alle PHP `php -l` OK, normberekening (jr1=0/10 t/m jr4+=8/40) getest, PDF visueel gerenderd (matcht BCND-formulier), React dev-app compileert nog.
- NIET getest op echte WordPress-installatie (geen WP-runtime in deze omgeving). Installeer op een schone WP-site en doorloop de end-to-end flow.

## Next tasks
- Installeer /app/bcnd-jaarformulier.zip op een echte WordPress-site en valideer de end-to-end flow.
- Zie backlog P1 items (herinneringen + e-mail) indien gewenst.
