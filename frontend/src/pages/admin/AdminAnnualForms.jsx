import { useEffect, useState, useCallback } from "react";
import api from "@/lib/api";
import { StatusBadge } from "@/components/StatusBadge";
import { DocumentChip } from "@/components/DocumentUpload";
import { Timeline, formatDate } from "@/components/Timeline";
import { ACTIVITY_LABEL } from "@/lib/constants";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { toast } from "sonner";
import { Loader2, FileText, Check, MessageSquareWarning, X, FileCog, FileDown, History } from "lucide-react";

const STATUSES = ["concept", "ingediend", "in_beoordeling", "aanpassing_gevraagd", "goedgekeurd", "afgekeurd"];

export default function AdminAnnualForms() {
  const [items, setItems] = useState([]);
  const [f, setF] = useState({ year: "", status: "" });
  const [detailId, setDetailId] = useState(null);

  const load = useCallback(() => {
    const p = new URLSearchParams();
    Object.entries(f).forEach(([k, v]) => v && p.append(k, v));
    api.get(`/annual-forms/admin/list?${p.toString()}`).then(({ data }) => setItems(data)).catch(() => {});
  }, [f]);
  useEffect(() => { load(); }, [load]);

  return (
    <div className="space-y-6" data-testid="admin-annual-forms">
      <div>
        <p className="overline text-terracotta">Beoordeling</p>
        <h1 className="font-heading text-3xl font-semibold tracking-tight text-forest mt-1">Jaarformulieren</h1>
      </div>

      <Card className="p-4 border-neutral-200">
        <div className="grid md:grid-cols-3 gap-3">
          <div>
            <Label className="overline text-neutral-600">Jaar</Label>
            <Input type="number" placeholder="Alle" className="mt-1.5" data-testid="form-filter-year"
              value={f.year} onChange={(e) => setF({ ...f, year: e.target.value })} />
          </div>
          <div>
            <Label className="overline text-neutral-600">Status</Label>
            <Select value={f.status || "all"} onValueChange={(v) => setF({ ...f, status: v === "all" ? "" : v })}>
              <SelectTrigger className="mt-1.5" data-testid="form-filter-status"><SelectValue placeholder="Alle" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Alle</SelectItem>
                {STATUSES.map((s) => <SelectItem key={s} value={s}>{s.replace(/_/g, " ")}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
        </div>
      </Card>

      <Card className="border-neutral-200 overflow-hidden">
        {items.length === 0 ? (
          <div className="px-6 py-16 text-center text-neutral-400">
            <FileText className="h-8 w-8 mx-auto mb-2 opacity-40" /> Geen jaarformulieren gevonden.
          </div>
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 text-neutral-500 text-left">
              <tr>
                <th className="px-5 py-3 font-medium">Lid</th>
                <th className="px-3 py-3 font-medium">Jaar</th>
                <th className="px-3 py-3 font-medium">Punten</th>
                <th className="px-3 py-3 font-medium">Consulten</th>
                <th className="px-3 py-3 font-medium">Norm</th>
                <th className="px-3 py-3 font-medium">Status</th>
                <th className="px-5 py-3"></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {items.map((form) => (
                <tr key={form.id} className="hover:bg-neutral-50 transition-colors">
                  <td className="px-5 py-3 font-medium text-neutral-800">{form.member_name}</td>
                  <td className="px-3 py-3">{form.year}</td>
                  <td className="px-3 py-3 text-neutral-600">{form.achieved_points_live}/{form.required_points_live}</td>
                  <td className="px-3 py-3 text-neutral-600">{form.achieved_consults_live}/{form.required_consults_live}</td>
                  <td className="px-3 py-3">
                    <span className={`text-xs font-medium ${form.norm_met ? "text-green-700" : "text-orange-700"}`}>
                      {form.norm_met ? "Behaald" : "Niet behaald"}
                    </span>
                  </td>
                  <td className="px-3 py-3"><StatusBadge status={form.status} /></td>
                  <td className="px-5 py-3 text-right">
                    <Button size="sm" variant="outline" data-testid={`form-review-${form.id}`} onClick={() => setDetailId(form.id)}>Beoordelen</Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>

      <FormReviewSheet formId={detailId} onClose={() => setDetailId(null)} onChange={load} />
    </div>
  );
}

function FormReviewSheet({ formId, onClose, onChange }) {
  const [data, setData] = useState(null);
  const [remark, setRemark] = useState("");
  const [history, setHistory] = useState([]);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!formId) { setData(null); return; }
    api.get(`/annual-forms/admin/${formId}`).then(({ data }) => setData(data)).catch(() => {});
    api.get(`/annual-forms/${formId}/history`).then(({ data }) => setHistory(data)).catch(() => {});
    setRemark("");
  }, [formId]);

  if (!formId) return null;

  const act = async (action) => {
    setBusy(true);
    try {
      await api.post(`/annual-forms/admin/${formId}/review`, { action, remark });
      toast.success("Beoordeling opgeslagen");
      onChange();
      const { data } = await api.get(`/annual-forms/admin/${formId}`);
      setData(data);
    } catch (e) { toast.error(e.response?.data?.detail || "Mislukt"); }
    finally { setBusy(false); }
  };

  const genPdf = async () => {
    setBusy(true);
    try {
      await api.post(`/annual-forms/admin/${formId}/generate-pdf`);
      toast.success("Definitieve PDF gegenereerd");
      const { data } = await api.get(`/annual-forms/admin/${formId}`);
      setData(data);
      onChange();
    } catch (e) { toast.error(e.response?.data?.detail || "Mislukt"); }
    finally { setBusy(false); }
  };

  const viewPdf = async () => {
    try {
      const res = await api.get(`/annual-forms/${formId}/pdf`, { responseType: "blob" });
      window.open(URL.createObjectURL(res.data), "_blank");
    } catch { toast.error("Geen PDF beschikbaar"); }
  };

  return (
    <Sheet open={!!formId} onOpenChange={(o) => !o && onClose()}>
      <SheetContent className="w-full sm:max-w-xl overflow-y-auto" data-testid="form-review-sheet">
        <SheetHeader><SheetTitle className="font-heading text-forest">Jaarformulier beoordelen</SheetTitle></SheetHeader>
        {!data ? <div className="py-20 flex justify-center"><Loader2 className="h-5 w-5 animate-spin text-forest" /></div> : (
          <div className="mt-4 space-y-5">
            <div className="flex items-center justify-between">
              <div>
                <div className="font-heading font-semibold text-forest">{data.member.name} · {data.form.year}</div>
                <div className="text-xs text-neutral-500">Lidnr {data.member.member_number} · sinds {formatDate(data.member.license_since)}</div>
              </div>
              <StatusBadge status={data.form.status} />
            </div>

            <div className="grid grid-cols-2 gap-3">
              <Card className="p-4 border-neutral-200">
                <div className="overline text-neutral-500">Bijscholingspunten</div>
                <div className="font-heading text-xl font-semibold text-forest">{data.overview.points.achieved} / {data.overview.points.required}</div>
                <div className={`text-xs ${data.overview.points.complete ? "text-green-700" : "text-orange-700"}`}>
                  {data.overview.points.complete ? "Norm behaald" : `Nog ${data.overview.points.remaining} nodig`}
                </div>
              </Card>
              <Card className="p-4 border-neutral-200">
                <div className="overline text-neutral-500">Consulten</div>
                <div className="font-heading text-xl font-semibold text-forest">{data.overview.consults.achieved} / {data.overview.consults.required}</div>
                <div className={`text-xs ${data.overview.consults.complete ? "text-green-700" : "text-orange-700"}`}>
                  {data.overview.consults.complete ? "Norm behaald" : `Nog ${data.overview.consults.remaining} nodig`}
                </div>
              </Card>
            </div>

            <div>
              <div className="overline text-neutral-600 mb-2">Bijscholingen ({data.trainings.length})</div>
              <div className="space-y-2">
                {data.trainings.map((t) => (
                  <div key={t.id} className="rounded-lg border border-neutral-200 p-3">
                    <div className="flex items-center justify-between">
                      <div className="text-sm font-medium text-neutral-800">{t.subject}</div>
                      <div className="flex items-center gap-2">
                        {t.points != null && <span className="text-xs text-forest font-medium">{t.points} pt</span>}
                        <StatusBadge status={t.status} />
                      </div>
                    </div>
                    <div className="text-xs text-neutral-500 mt-0.5">{formatDate(t.date)} · {t.organization} · {ACTIVITY_LABEL[t.activity_type]}</div>
                    {t.documents?.length > 0 && <div className="flex gap-2 mt-2">{t.documents.map((d) => <DocumentChip key={d.id} doc={d} />)}</div>}
                  </div>
                ))}
                {data.trainings.length === 0 && <p className="text-sm text-neutral-400">Geen bijscholingen.</p>}
              </div>
            </div>

            {data.form.deviation_reason && (
              <div className="bg-orange-50 rounded-lg p-3">
                <div className="overline text-orange-700 mb-1">Toelichting afwijking van norm</div>
                <div className="text-sm text-orange-900">{data.form.deviation_reason}</div>
              </div>
            )}

            <div>
              <Label className="overline text-neutral-600">Opmerking</Label>
              <Textarea className="mt-1.5" rows={2} data-testid="form-review-remark" value={remark}
                onChange={(e) => setRemark(e.target.value)} placeholder="Motivatie / reden voor correctie…" />
            </div>

            <div className="grid grid-cols-3 gap-2">
              <Button className="bg-forest hover:bg-forest-hover text-white" data-testid="form-approve-button" disabled={busy} onClick={() => act("approve")}>
                <Check className="h-4 w-4 mr-1" /> Goedkeuren
              </Button>
              <Button variant="outline" className="border-orange-300 text-orange-700 hover:bg-orange-50" data-testid="form-correction-button" disabled={busy} onClick={() => act("request_correction")}>
                <MessageSquareWarning className="h-4 w-4 mr-1" /> Correctie
              </Button>
              <Button variant="outline" className="border-red-300 text-red-700 hover:bg-red-50" data-testid="form-reject-button" disabled={busy} onClick={() => act("reject")}>
                <X className="h-4 w-4 mr-1" /> Afwijzen
              </Button>
            </div>

            <div className="flex gap-2 pt-2 border-t border-neutral-100">
              <Button variant="outline" className="flex-1" data-testid="generate-pdf-button" disabled={busy} onClick={genPdf}>
                <FileCog className="h-4 w-4 mr-2" /> Definitieve PDF genereren
              </Button>
              {data.form.pdf_document_id && (
                <Button variant="ghost" className="text-forest" data-testid="view-pdf-button" onClick={viewPdf}>
                  <FileDown className="h-4 w-4 mr-2" /> Bekijk PDF
                </Button>
              )}
            </div>

            <div>
              <div className="flex items-center gap-1.5 overline text-neutral-600 mb-2"><History className="h-3.5 w-3.5" /> Historie</div>
              <Timeline items={history} />
            </div>
          </div>
        )}
      </SheetContent>
    </Sheet>
  );
}
