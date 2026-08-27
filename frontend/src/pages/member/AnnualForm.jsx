import { useEffect, useState } from "react";
import api from "@/lib/api";
import { StatusBadge } from "@/components/StatusBadge";
import { formatDate } from "@/components/Timeline";
import { ACTIVITY_LABEL } from "@/lib/constants";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogTrigger,
} from "@/components/ui/dialog";
import { toast } from "sonner";
import { Loader2, Send, FileDown, AlertTriangle, CheckCircle2, Lock } from "lucide-react";

const NOW = new Date().getFullYear();

export default function AnnualForm() {
  const [year, setYear] = useState(NOW);
  const [data, setData] = useState(null);
  const [reason, setReason] = useState("");
  const [open, setOpen] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  const load = (y) => {
    setData(null);
    api.get(`/annual-forms?year=${y}`).then(({ data }) => { setData(data); setReason(data.form.deviation_reason || ""); }).catch(() => {});
  };
  useEffect(() => { load(year); }, [year]);

  if (!data) return <div className="py-20 flex justify-center"><Loader2 className="h-6 w-6 animate-spin text-forest" /></div>;

  const { form, overview, trainings, member } = data;
  const locked = ["ingediend", "in_beoordeling", "goedgekeurd"].includes(form.status);
  const needsReason = !overview.all_complete;

  const submit = async () => {
    if (needsReason && !reason.trim()) { toast.error("Een toelichting is vereist omdat de norm niet is behaald"); return; }
    setSubmitting(true);
    try {
      await api.post(`/annual-forms/${year}/submit`, { deviation_reason: reason });
      toast.success("Jaarformulier ingediend");
      setOpen(false); load(year);
    } catch (e) { toast.error(e.response?.data?.detail || "Indienen mislukt"); }
    finally { setSubmitting(false); }
  };

  const downloadPdf = async () => {
    try {
      const res = await api.get(`/annual-forms/${form.id}/pdf`, { responseType: "blob" });
      window.open(URL.createObjectURL(res.data), "_blank");
    } catch { toast.error("Nog geen PDF beschikbaar"); }
  };

  return (
    <div className="space-y-6 max-w-4xl" data-testid="annual-form-page">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <p className="overline text-terracotta">Jaarformulier</p>
          <h1 className="font-heading text-3xl font-semibold tracking-tight text-forest mt-1">Jaarformulier {year}</h1>
        </div>
        <div className="flex items-center gap-3">
          <Input type="number" className="w-28" data-testid="form-year" value={year} onChange={(e) => setYear(e.target.value)} />
          <StatusBadge status={form.status} testid="form-status" />
        </div>
      </div>

      {/* Status banner */}
      {locked ? (
        <Card className="p-4 border-neutral-200 bg-blue-50/40 flex items-center gap-3" data-testid="locked-banner">
          <Lock className="h-5 w-5 text-blue-700" />
          <div className="text-sm text-blue-900">
            {form.status === "goedgekeurd"
              ? "Dit jaarformulier is goedgekeurd door de BCND."
              : "Ingediend — wacht op beoordeling door BCND. De gegevens kunnen niet meer worden gewijzigd."}
          </div>
        </Card>
      ) : (
        <Card className={`p-4 border-neutral-200 flex items-center gap-3 ${overview.all_complete ? "bg-green-50/50" : "bg-orange-50/50"}`}>
          {overview.all_complete ? <CheckCircle2 className="h-5 w-5 text-green-700" /> : <AlertTriangle className="h-5 w-5 text-orange-700" />}
          <div className="text-sm">
            {overview.all_complete
              ? "Je voldoet aan alle normen. Je kunt het jaarformulier indienen."
              : "Norm niet behaald — je kunt alsnog indienen met een verplichte toelichting."}
          </div>
        </Card>
      )}
      {form.admin_remark && form.status === "aanpassing_gevraagd" && (
        <Card className="p-4 border-orange-200 bg-orange-50" data-testid="form-admin-remark">
          <div className="overline text-orange-700 mb-1">Correctie gevraagd</div>
          <div className="text-sm text-orange-900">{form.admin_remark}</div>
        </Card>
      )}

      {/* Document preview */}
      <Card className="border-neutral-200 overflow-hidden">
        <div className="bg-forest text-white px-8 py-5">
          <h2 className="doc-preview text-xl font-bold">BCND Jaarformulier Licentieleden</h2>
          <p className="text-white/60 text-xs mt-0.5">Automatisch samengesteld uit uw registraties</p>
        </div>
        <div className="p-8 doc-preview space-y-8">
          <section>
            <h3 className="text-forest font-bold border-b border-neutral-200 pb-1 mb-3">Licentielid</h3>
            <div className="grid grid-cols-2 gap-y-2 gap-x-8 text-sm">
              <Row label="Naam" value={member.name} />
              <Row label="Lidnummer BCND" value={member.member_number} />
              <Row label="Adres" value={member.address} />
              <Row label="Plaats" value={member.city} />
              <Row label="Licentielid sinds" value={formatDate(member.license_since)} />
              <Row label="Datum" value={formatDate(new Date().toISOString())} />
            </div>
          </section>

          <section>
            <h3 className="text-forest font-bold border-b border-neutral-200 pb-1 mb-3">
              Gevolgde bijscholingen · minimaal {overview.points.required} punten
            </h3>
            {trainings.length === 0 ? <p className="text-sm text-neutral-400">Geen bijscholingen geregistreerd.</p> : (
              <table className="w-full text-xs">
                <thead>
                  <tr className="text-left text-neutral-500 border-b border-neutral-200">
                    <th className="py-1.5 pr-2">Datum</th><th className="pr-2">Uren</th><th className="pr-2">Organisatie</th>
                    <th className="pr-2">Onderwerp</th><th className="pr-2">Spreker</th><th className="pr-2">Type</th><th>Punten</th>
                  </tr>
                </thead>
                <tbody>
                  {trainings.map((t) => (
                    <tr key={t.id} className="border-b border-neutral-100 align-top">
                      <td className="py-1.5 pr-2 whitespace-nowrap">{formatDate(t.date)}</td>
                      <td className="pr-2">{t.hours}</td>
                      <td className="pr-2">{t.organization}</td>
                      <td className="pr-2"><b>{t.subject}</b><br /><span className="text-neutral-500">{t.content_explanation}</span></td>
                      <td className="pr-2">{t.speaker}</td>
                      <td className="pr-2">{ACTIVITY_LABEL[t.activity_type]}</td>
                      <td>{t.status === "goedgekeurd" ? t.points : <span className="text-neutral-400">–</span>}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
            <div className="text-right text-sm mt-3 font-bold text-forest" data-testid="total-points">
              Totaal goedgekeurde punten: {overview.points.achieved} / {overview.points.required}
            </div>
          </section>

          <section>
            <h3 className="text-forest font-bold border-b border-neutral-200 pb-1 mb-3">Consulten</h3>
            <div className="grid grid-cols-2 gap-y-2 gap-x-8 text-sm">
              <Row label="Totaal aantal consulten" value={`${overview.consults.achieved} / ${overview.consults.required}`} />
              <Row label="Aantal 1e consulten" value={overview.consults.first_consults} />
              <Row label="Aantal vervolgconsulten" value={overview.consults.followup_consults} />
              <Row label="Overige activiteiten" value={overview.consults.other_activities || "—"} />
            </div>
          </section>

          {needsReason && (
            <section>
              <h3 className="text-forest font-bold border-b border-neutral-200 pb-1 mb-3">Toelichting bij afwijking van de norm</h3>
              {locked ? (
                <p className="text-sm text-neutral-700">{form.deviation_reason || "—"}</p>
              ) : (
                <Textarea data-testid="deviation-reason" rows={3} value={reason} onChange={(e) => setReason(e.target.value)}
                  placeholder="Wat is de reden en hoe denkt u de achterstand in te halen?" />
              )}
            </section>
          )}
        </div>
      </Card>

      {/* Actions */}
      <div className="flex items-center gap-3">
        {!locked && (
          <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
              <Button className="bg-forest hover:bg-forest-hover text-white" data-testid="open-submit-dialog">
                <Send className="h-4 w-4 mr-2" /> Jaarformulier indienen
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader><DialogTitle className="font-heading text-forest">Jaarformulier {year} indienen</DialogTitle></DialogHeader>
              <div className="space-y-3 py-2 text-sm">
                <p className="text-neutral-600">Na indienen kunt u de gegevens niet meer wijzigen totdat de administratie het formulier eventueel terugstuurt.</p>
                {needsReason && (
                  <div className="bg-orange-50 rounded-md p-3">
                    <div className="flex items-center gap-2 text-orange-800 font-medium mb-2"><AlertTriangle className="h-4 w-4" /> Norm niet behaald — toelichting vereist</div>
                    <Textarea data-testid="dialog-reason" rows={3} value={reason} onChange={(e) => setReason(e.target.value)}
                      placeholder="Reden en plan om de achterstand in te halen…" />
                  </div>
                )}
              </div>
              <DialogFooter>
                <Button variant="outline" onClick={() => setOpen(false)}>Annuleren</Button>
                <Button className="bg-forest hover:bg-forest-hover text-white" data-testid="confirm-submit-button" disabled={submitting} onClick={submit}>
                  {submitting && <Loader2 className="h-4 w-4 mr-2 animate-spin" />} Definitief indienen
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        )}
        {form.pdf_document_id && (
          <Button variant="outline" onClick={downloadPdf} data-testid="download-pdf-button">
            <FileDown className="h-4 w-4 mr-2" /> Definitief PDF bekijken
          </Button>
        )}
      </div>
    </div>
  );
}

function Row({ label, value }) {
  return (
    <div className="flex justify-between border-b border-dotted border-neutral-200 pb-1">
      <span className="text-neutral-500">{label}</span>
      <span className="font-medium text-neutral-800 text-right">{value || "—"}</span>
    </div>
  );
}
