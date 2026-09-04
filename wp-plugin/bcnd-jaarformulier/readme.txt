=== BCND Jaarformulier & Nascholingsadministratie ===
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0.0

Zelfstandige WordPress-plugin voor de BCND: registreren, beoordelen en verwerken
van bij- en nascholingen, consulten en jaarformulieren van licentieleden.

== Beschrijving ==

* Ledenportaal via de shortcode `[bcnd_portal]` (plaats deze op een normale WordPress-pagina).
* Beheeromgeving onder het WordPress-menu **BCND** (Dashboard, Leden, Bijscholingen, Jaarformulieren, Instellingen).
* Eigen databasetabellen (wp_bcnd_*), aangemaakt via dbDelta bij activatie/upgrade.
* Namespaced REST API onder `/wp-json/bcnd/v1/` met server-side autorisatie.
* Rollen/capabilities: BCND Administrator (bcnd_admin) en BCND Licentielid (bcnd_member).
* PDF-generatie van het jaarformulier (zonder externe libraries).
* Notificaties via wp_mail + in-app inbox. Herinneringen via WP-cron.
* Documenten (deelnamebewijzen) worden privé opgeslagen buiten publieke URL's en
  uitsluitend via de REST API met permissiecontrole geserveerd.

== Installatie ==

1. Ga naar WordPress → Plugins → Nieuwe plugin → Plugin uploaden.
2. Upload `bcnd-jaarformulier.zip` en activeer de plugin.
3. Maak een pagina aan met de shortcode `[bcnd_portal]` voor het ledenportaal.
4. Beheer via het menu **BCND** in wp-admin.
5. Maak leden aan via BCND → Leden → Nieuw lid (dit maakt automatisch een WordPress-gebruiker met de rol Licentielid).

Een WordPress-beheerder heeft automatisch alle BCND-rechten. Wijs anders de rol
"BCND Administrator" toe aan de betreffende gebruiker.

== Rechten (capabilities) ==

* bcnd_manage_members
* bcnd_manage_training
* bcnd_review_training
* bcnd_manage_consultations
* bcnd_review_annual_forms
* bcnd_manage_documents
* bcnd_manage_settings

== Data behoud ==

Bij plugin-upgrades wordt het schema alleen additief bijgewerkt (dbDelta); bestaande
data blijft behouden. Bij verwijderen blijven de tabellen standaard staan zodat
goedgekeurde historische jaarformulieren auditeerbaar blijven. Definieer
`BCND_REMOVE_ALL_DATA` als `true` in wp-config.php om bij uninstall alles te wissen.
