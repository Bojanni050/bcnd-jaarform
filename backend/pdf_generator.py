from io import BytesIO
from datetime import datetime

from reportlab.lib import colors
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import mm
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, HRFlowable,
)

FOREST = colors.HexColor("#1E3F33")
SAGE = colors.HexColor("#8A9A86")
LIGHT = colors.HexColor("#F0EEE4")

ACTIVITY_LABELS = {
    "externe_bijscholing": "Externe bijscholing",
    "bcnd_bijscholing": "BCND-bijscholing",
    "bcnd_ledenbijeenkomst": "BCND ledenbijeenkomst",
    "overige_activiteit": "Overige activiteit",
}


def _styles():
    ss = getSampleStyleSheet()
    ss.add(ParagraphStyle(name="BTitle", fontName="Helvetica-Bold", fontSize=18,
                          textColor=FOREST, spaceAfter=2))
    ss.add(ParagraphStyle(name="BSub", fontName="Helvetica", fontSize=9,
                          textColor=colors.HexColor("#5C584A"), spaceAfter=4))
    ss.add(ParagraphStyle(name="BSection", fontName="Helvetica-Bold", fontSize=12,
                          textColor=FOREST, spaceBefore=12, spaceAfter=6))
    ss.add(ParagraphStyle(name="BLabel", fontName="Helvetica-Bold", fontSize=8,
                          textColor=colors.HexColor("#4A463B")))
    ss.add(ParagraphStyle(name="BVal", fontName="Helvetica", fontSize=9))
    ss.add(ParagraphStyle(name="BCell", fontName="Helvetica", fontSize=7.5, leading=9))
    ss.add(ParagraphStyle(name="BCellH", fontName="Helvetica-Bold", fontSize=7.5,
                          leading=9, textColor=colors.white))
    return ss


def generate_annual_pdf(member, form, trainings, overview, norms) -> bytes:
    buf = BytesIO()
    doc = SimpleDocTemplate(buf, pagesize=A4, topMargin=16 * mm, bottomMargin=16 * mm,
                            leftMargin=16 * mm, rightMargin=16 * mm)
    ss = _styles()
    e = []

    e.append(Paragraph("BCND Jaarformulier Licentieleden", ss["BTitle"]))
    e.append(Paragraph("Beroepsvereniging van Complementaire en Natuurlijke geneeswijzen voor Dieren",
                       ss["BSub"]))
    e.append(HRFlowable(width="100%", thickness=1.5, color=FOREST, spaceAfter=8))

    status = form.get("status", "concept")
    e.append(Paragraph(f"Jaar {form.get('year')} &nbsp;&nbsp;|&nbsp;&nbsp; Status: <b>{status.replace('_',' ').title()}</b>", ss["BVal"]))

    # Licentielid
    e.append(Paragraph("Licentielid", ss["BSection"]))
    info = [
        ["Naam", member.get("name", ""), "Lidnummer BCND", member.get("member_number", "")],
        ["Adres", member.get("address", ""), "Plaats", member.get("city", "")],
        ["Licentielid sinds", str(member.get("license_since", ""))[:10],
         "Datum", datetime.now().strftime("%d-%m-%Y")],
    ]
    t = Table(info, colWidths=[32 * mm, 60 * mm, 32 * mm, 44 * mm])
    t.setStyle(TableStyle([
        ("FONTNAME", (0, 0), (0, -1), "Helvetica-Bold"),
        ("FONTNAME", (2, 0), (2, -1), "Helvetica-Bold"),
        ("FONTSIZE", (0, 0), (-1, -1), 8.5),
        ("TEXTCOLOR", (0, 0), (0, -1), FOREST),
        ("TEXTCOLOR", (2, 0), (2, -1), FOREST),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
        ("LINEBELOW", (0, 0), (-1, -2), 0.4, LIGHT),
    ]))
    e.append(t)

    # Bijscholingen
    e.append(Paragraph("Gevolgde bijscholingen (minimaal 8 punten per jaar)", ss["BSection"]))
    head = [Paragraph(h, ss["BCellH"]) for h in
            ["Datum", "Uren", "Organisatie", "Onderwerp + inhoud/leerdoel", "Spreker", "Type", "Punten"]]
    rows = [head]
    for tr in trainings:
        rows.append([
            Paragraph(str(tr.get("date", ""))[:10], ss["BCell"]),
            Paragraph(str(tr.get("hours", "")), ss["BCell"]),
            Paragraph(tr.get("organization", ""), ss["BCell"]),
            Paragraph(f"<b>{tr.get('subject','')}</b><br/>{tr.get('content_explanation','')}", ss["BCell"]),
            Paragraph(tr.get("speaker", ""), ss["BCell"]),
            Paragraph(ACTIVITY_LABELS.get(tr.get("activity_type", ""), ""), ss["BCell"]),
            Paragraph(str(tr.get("points") if tr.get("points") is not None else "-"), ss["BCell"]),
        ])
    tt = Table(rows, colWidths=[18 * mm, 12 * mm, 30 * mm, 58 * mm, 24 * mm, 22 * mm, 14 * mm], repeatRows=1)
    tt.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), FOREST),
        ("GRID", (0, 0), (-1, -1), 0.4, LIGHT),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, colors.HexColor("#F9F9F6")]),
        ("TOPPADDING", (0, 0), (-1, -1), 4),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
    ]))
    e.append(tt)
    e.append(Spacer(1, 4))
    e.append(Paragraph(
        f"<b>Totaal goedgekeurde punten:</b> {overview['points']['achieved']} / {norms['points_norm']}",
        ss["BVal"]))

    # Consulten
    e.append(Paragraph("Consulten", ss["BSection"]))
    c = overview["consults"]
    crows = [
        ["Totaal aantal consulten", str(c["achieved"]), "Norm", str(norms["consults_norm"])],
        ["Aantal 1e consulten", str(c["first_consults"]), "", ""],
        ["Aantal vervolgconsulten", str(c["followup_consults"]), "", ""],
        ["Overige activiteiten", c.get("other_activities", "") or "-", "", ""],
    ]
    ct = Table(crows, colWidths=[50 * mm, 40 * mm, 30 * mm, 44 * mm])
    ct.setStyle(TableStyle([
        ("FONTNAME", (0, 0), (0, -1), "Helvetica-Bold"),
        ("FONTNAME", (2, 0), (2, -1), "Helvetica-Bold"),
        ("FONTSIZE", (0, 0), (-1, -1), 8.5),
        ("TEXTCOLOR", (0, 0), (0, -1), FOREST),
        ("LINEBELOW", (0, 0), (-1, -1), 0.4, LIGHT),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
    ]))
    e.append(ct)

    # Afwijking van norm
    if form.get("deviation_reason"):
        e.append(Paragraph("Toelichting bij afwijking van de norm", ss["BSection"]))
        e.append(Paragraph(form.get("deviation_reason", ""), ss["BVal"]))

    e.append(Spacer(1, 14))
    e.append(HRFlowable(width="100%", thickness=0.6, color=SAGE, spaceAfter=6))
    gen = datetime.now().strftime("%d-%m-%Y %H:%M")
    e.append(Paragraph(
        f"Automatisch gegenereerd door de BCND Nascholingsadministratie op {gen}. "
        f"Toegepaste normen: {norms['points_norm']} punten / {norms['consults_norm']} consulten "
        f"(lidmaatschapsjaar {norms['membership_year']}).", ss["BSub"]))

    doc.build(e)
    return buf.getvalue()
