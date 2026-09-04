import { useEffect, useState, useCallback } from "react";
import api from "@/lib/api";
import { StatusBadge } from "@/components/StatusBadge";
import { DocumentChip } from "@/components/DocumentUpload";
import { Timeline, formatDate } from "@/components/Timeline";
import { ACTIVITY_TYPES, ACTIVITY_LABEL } from "@/lib/constants";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { toast } from "sonner";
import { Loader2, ClipboardCheck, Check, X, MessageSquareWarning, History } from "lucide-react";

const YEAR = new Date().getFullYear();
const STATUSES = ["ingediend", "in_beoordeling", "aanpassing_gevraagd", "goedgekeurd", "afgekeurd", "concept"];

export default function AdminTrainings() {
  const [items, setItems] = useState([]);
  const [f, setF] = useState({ year: "", status: "", organization: "", activity_type: "" });
  const [detail, setDetail] = useState(null);

  const load = useCallback(() => {
    const p = new URLSearchParams();
    Object.entries(f).forEach(([k, v]) => v && p.append(k, v));
    api.get(`/trainings?${p.toString()}`).then(({ data }) => setItems(data)).catch(() => {});
  }, [f]);
  useEffect(() => { load(); }, [load]);

  return (
    <div className="space-y-6" data-testid="admin-trainings">
      <div>
        <p className="overline text-terracotta">Beoordeling</p>
        <h1 className="font-heading text-3xl font-semibold tracking-tight text-forest mt-1">Bijscholingen</h1>
      </div>

      <Card className="p-4 border-neutral-200">
        <div className="grid md:grid-cols-4 gap-3">
          <div>
            <Label className="overline text-neutral-600">Jaar</Label>
            <Input type="number" placeholder="Alle" className="mt-1.5" data-testid="filter-year"
              value={f.year} onChange={(e) => setF({ ...f, year: e.target.value })} />
          </div>
          <div>
            <Label className="overline text-neutral-600">Status</Label>
            <Select value={f.status || "all"} onValueChange={(v) => setF({ ...f, status: v === "all" ? "" : v })}>
              <SelectTrigger className="mt-1.5" data-testid="filter-status"><SelectValue placeholder="Alle" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Alle</SelectItem>
                {STATUSES.map((s) => <SelectItem key={s} value={s}>{s.replace(/_/g, " ")}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
          <div>
            <Label className="overline text-neutral-600">Type</Label>
            <Select value={f.activity_type || "all"} onValueChange={(v) => setF({ ...f, activity_type: v === "all" ? "" : v })}>
              <SelectTrigger className="mt-1.5" data-testid="filter-type"><SelectValue placeholder="Alle" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Alle</SelectItem>
                {ACTIVITY_TYPES.map((a) => <SelectItem key={a.value} value={a.value}>{a.label}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
          <div>
            <Label className="overline text-neutral-600">Organisatie</Label>
            <Input placeholder="Zoek…" className="mt-1.5" data-testid="filter-organization"
              value={f.organization} onChange={(e) => setF({ ...f, organization: e.target.value })} />
          </div>
        </div>
      </Card>

      <Card className="border-neutral-200 overflow-hidden">
        {items.length === 0 ? (
          <div className="px-6 py-16 text-center text-neutral-400">
            <ClipboardCheck className="h-8 w-8 mx-auto mb-2 opacity-40" /> Geen bijscholingen gevonden.
          </div>
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 text-neutral-500">
              <tr className="text-left">
                <th className="px-5 py-3 font-medium">Lid</th>
                <th className="px-3 py-3 font-medium">Datum</th>
                <th className="px-3 py-3 font-medium">Onderwerp</th>
                <th className="px-3 py-3 font-medium">Type</th>
                <th className="px-3 py-3 font-medium">Punten</th>
                <th className="px-3 py-3 font-medium">Status</th>
                <th className="px-5 py-3"></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {items.map((t) => (
                <tr key={t.id} className="hover:bg-neutral-50 transition-colors" data-testid={`admin-training-${t.id}`}>
                  <td className="px-5 py-3 font-medium text-neutral-800">{t.member_name}</td>
                  <td className="px-3 py-3 whitespace-nowrap text-neutral-600">{formatDate(t.date)}</td>
                  <td className="px-3 py-3 text-neutral-700 max-w-[220px] truncate">{t.subject}</td>
                  <td className="px-3 py-3 text-neutral-600">{ACTIVITY_LABEL[t.activity_type]}</td>
                  <td className="px-3 py-3 text-forest font-medium">{t.points != null ? t.points : "—"}</td>
                  <td className="px-3 py-3"><StatusBadge status={t.status} /></td>
                  <td className="px-5 py-3 text-right">
                    <Button size="sm" variant="outline" data-testid={`review-${t.id}`} onClick={() => setDetail(t)}>Beoordelen</Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>

      <ReviewSheet training={detail} onClose={() => setDetail(null)} onChange={load} />
    </div>
  );
}

function ReviewSheet({ training, onClose, onChange }) {
  const [points, setPoints] = useState("");
  const [pointsEdited, setPointsEdited] = useState(false);
  const [remark, setRemark] = useState("");
  const [history, setHistory] = useState([]);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!training) return;
    setPoints(training.points != null ? String(training.points) : "");
    setPointsEdited(false);
    setRemark("");
    api.get(`/trainings/${training.id}/history`).then(({ data }) => setHistory(data)).catch(() => {});
  }, [training]);
  if (!training) return null;

  const act = async (action) => {
    setBusy(true);
    try {
      // Only send points when the admin actually changed the field, so approving
      // an already-graded training never silently overwrites its stored points.
      const pointsPayload = pointsEdited ? (points === "" ? null : parseFloat(points)) : null;
      await api.post(`/trainings/${training.id}/review`, {
        action, points: pointsPayload, remark,
      });
      toast.success("Beoordeling opgeslagen");
      onChange(); onClose();
    } catch (e) { toast.error(e.response?.data?.detail || "Mislukt"); }
    finally { setBusy(false); }
  };

  return (
    <Sheet open={!!training} onOpenChange={(o) => !o && onClose()}>
      <SheetContent className="w-full sm:max-w-lg overflow-y-auto" data-testid="review-sheet">
        <SheetHeader><SheetTitle className="font-heading text-forest">Bijscholing beoordelen</SheetTitle></SheetHeader>
        <div className="mt-4 space-y-5">
          <div className="grid grid-cols-2 gap-3 text-sm bg-neutral-50 rounded-lg p-4">
            <Info label="Lid" value={training.member_name} />
            <Info label="Datum" value={formatDate(training.date)} />
            <Info label="Organisatie" value={training.organization} />
            <Info label="Spreker" value={training.speaker} />
            <Info label="Uren" value={training.hours} />
            <Info label="Type" value={ACTIVITY_LABEL[training.activity_type]} />
            <div className="col-span-2"><Info label="Onderwerp" value={training.subject} /></div>
            <div className="col-span-2"><Info label="Inhoud / leerdoel" value={training.content_explanation} /></div>
            {training.member_remarks && <div className="col-span-2"><Info label="Opmerking lid" value={training.member_remarks} /></div>}
          </div>

          <div>
            <div className="overline text-neutral-600 mb-2">Deelnamebewijzen</div>
            <div className="flex flex-wrap gap-2">
              {training.documents?.length ? training.documents.map((d) => <DocumentChip key={d.id} doc={d} />)
                : <span className="text-sm text-neutral-400">Geen — bij BCND-activiteiten via presentielijst</span>}
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label className="overline text-neutral-600">Toegekende punten</Label>
              <Input type="number" step="0.5" className="mt-1.5" data-testid="assign-points" value={points}
                onChange={(e) => { setPoints(e.target.value); setPointsEdited(true); }} />
            </div>
          </div>
          <div>
            <Label className="overline text-neutral-600">Opmerking</Label>
            <Textarea className="mt-1.5" rows={3} data-testid="review-remark" value={remark}
              onChange={(e) => setRemark(e.target.value)} placeholder="Optionele opmerking / motivatie…" />
          </div>

          <div className="grid grid-cols-3 gap-2">
            <Button className="bg-forest hover:bg-forest-hover text-white" data-testid="approve-button" disabled={busy} onClick={() => act("approve")}>
              <Check className="h-4 w-4 mr-1" /> Goedkeuren
            </Button>
            <Button variant="outline" className="border-orange-300 text-orange-700 hover:bg-orange-50" data-testid="request-changes-button" disabled={busy} onClick={() => act("request_changes")}>
              <MessageSquareWarning className="h-4 w-4 mr-1" /> Aanpassing
            </Button>
            <Button variant="outline" className="border-red-300 text-red-700 hover:bg-red-50" data-testid="reject-button" disabled={busy} onClick={() => act("reject")}>
              <X className="h-4 w-4 mr-1" /> Afkeuren
            </Button>
          </div>

          <div>
            <div className="flex items-center gap-1.5 overline text-neutral-600 mb-2"><History className="h-3.5 w-3.5" /> Historie</div>
            <Timeline items={history} />
          </div>
        </div>
      </SheetContent>
    </Sheet>
  );
}

function Info({ label, value }) {
  return <div><div className="overline text-neutral-500">{label}</div><div className="text-sm text-neutral-800">{value || "—"}</div></div>;
}
