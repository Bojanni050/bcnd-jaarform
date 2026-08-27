import { useEffect, useState, useCallback } from "react";
import api from "@/lib/api";
import { StatusBadge } from "@/components/StatusBadge";
import { DocumentUpload, DocumentChip } from "@/components/DocumentUpload";
import { Timeline, formatDate } from "@/components/Timeline";
import { ACTIVITY_TYPES, ACTIVITY_LABEL } from "@/lib/constants";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter,
} from "@/components/ui/dialog";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/components/ui/select";
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { toast } from "sonner";
import { Plus, GraduationCap, Info, History } from "lucide-react";

const empty = {
  date: "", hours: "", organization: "", subject: "", content_explanation: "",
  speaker: "", activity_type: "externe_bijscholing", member_remarks: "",
};

export default function Trainings() {
  const [items, setItems] = useState([]);
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState(empty);
  const [saving, setSaving] = useState(false);
  const [detail, setDetail] = useState(null);

  const load = useCallback(() => {
    api.get("/trainings").then(({ data }) => setItems(data)).catch(() => {});
  }, []);
  useEffect(() => { load(); }, [load]);

  const submit = async (status) => {
    if (!form.date || !form.subject) { toast.error("Datum en onderwerp zijn verplicht"); return; }
    setSaving(true);
    try {
      await api.post("/trainings", { ...form, hours: parseFloat(form.hours) || 0, status });
      toast.success(status === "ingediend" ? "Bijscholing ingediend" : "Concept opgeslagen");
      setOpen(false); setForm(empty); load();
    } catch (e) { toast.error(e.response?.data?.detail || "Opslaan mislukt"); }
    finally { setSaving(false); }
  };

  return (
    <div className="space-y-6" data-testid="trainings-page">
      <div className="flex items-center justify-between">
        <div>
          <p className="overline text-terracotta">Registraties</p>
          <h1 className="font-heading text-3xl font-semibold tracking-tight text-forest mt-1">Bijscholingen</h1>
        </div>
        <Dialog open={open} onOpenChange={setOpen}>
          <DialogTrigger asChild>
            <Button data-testid="new-training-button" className="bg-forest hover:bg-forest-hover text-white">
              <Plus className="h-4 w-4 mr-1.5" /> Nieuwe bijscholing
            </Button>
          </DialogTrigger>
          <DialogContent className="max-w-lg max-h-[90vh] overflow-y-auto">
            <DialogHeader><DialogTitle className="font-heading text-forest">Bijscholing registreren</DialogTitle></DialogHeader>
            <div className="space-y-3.5 py-2">
              <div className="grid grid-cols-2 gap-3">
                <Field label="Datum *"><Input type="date" data-testid="training-date" value={form.date}
                  onChange={(e) => setForm({ ...form, date: e.target.value })} /></Field>
                <Field label="Aantal uren"><Input type="number" step="0.5" data-testid="training-hours" value={form.hours}
                  onChange={(e) => setForm({ ...form, hours: e.target.value })} /></Field>
              </div>
              <Field label="Type activiteit">
                <Select value={form.activity_type} onValueChange={(v) => setForm({ ...form, activity_type: v })}>
                  <SelectTrigger data-testid="training-type"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {ACTIVITY_TYPES.map((a) => <SelectItem key={a.value} value={a.value}>{a.label}</SelectItem>)}
                  </SelectContent>
                </Select>
              </Field>
              <Field label="Organisatie"><Input data-testid="training-organization" value={form.organization}
                onChange={(e) => setForm({ ...form, organization: e.target.value })} /></Field>
              <Field label="Onderwerp *"><Input data-testid="training-subject" value={form.subject}
                onChange={(e) => setForm({ ...form, subject: e.target.value })} /></Field>
              <Field label="Korte uitleg inhoud / leerdoel"><Textarea data-testid="training-content" rows={2}
                value={form.content_explanation} onChange={(e) => setForm({ ...form, content_explanation: e.target.value })} /></Field>
              <Field label="Spreker"><Input data-testid="training-speaker" value={form.speaker}
                onChange={(e) => setForm({ ...form, speaker: e.target.value })} /></Field>
              <Field label="Opmerkingen"><Textarea data-testid="training-remarks" rows={2}
                value={form.member_remarks} onChange={(e) => setForm({ ...form, member_remarks: e.target.value })} /></Field>
              {(form.activity_type === "bcnd_bijscholing" || form.activity_type === "bcnd_ledenbijeenkomst") && (
                <div className="flex gap-2 text-xs text-neutral-500 bg-neutral-100 rounded-md p-2.5">
                  <Info className="h-4 w-4 shrink-0 text-sage" />
                  Voor BCND-activiteiten wordt de presentielijst rechtstreeks aan de administratie verstrekt. Een deelnamebewijs is niet vereist.
                </div>
              )}
            </div>
            <DialogFooter className="gap-2">
              <Button variant="outline" data-testid="save-concept-button" disabled={saving} onClick={() => submit("concept")}>
                Opslaan als concept
              </Button>
              <Button className="bg-forest hover:bg-forest-hover text-white" data-testid="submit-training-button"
                disabled={saving} onClick={() => submit("ingediend")}>Indienen</Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>

      <Card className="border-neutral-200">
        {items.length === 0 ? (
          <div className="px-6 py-16 text-center text-neutral-400">
            <GraduationCap className="h-8 w-8 mx-auto mb-2 opacity-40" />
            Nog geen bijscholingen. Klik op "Nieuwe bijscholing" om te beginnen.
          </div>
        ) : (
          <div className="divide-y divide-neutral-100">
            {items.map((t) => (
              <button key={t.id} data-testid={`training-row-${t.id}`} onClick={() => setDetail(t)}
                className="w-full text-left px-6 py-4 flex items-center justify-between hover:bg-neutral-50 transition-colors">
                <div className="min-w-0">
                  <div className="font-medium text-neutral-800 truncate">{t.subject}</div>
                  <div className="text-xs text-neutral-500 mt-0.5">
                    {formatDate(t.date)} · {t.organization || "—"} · {ACTIVITY_LABEL[t.activity_type]}
                  </div>
                </div>
                <div className="flex items-center gap-4 shrink-0">
                  {t.documents?.length > 0 && <span className="text-xs text-sage">{t.documents.length} doc</span>}
                  {t.points != null && <span className="text-sm text-forest font-medium">{t.points} pt</span>}
                  <StatusBadge status={t.status} />
                </div>
              </button>
            ))}
          </div>
        )}
      </Card>

      <TrainingDetail training={detail} onClose={() => setDetail(null)} onChange={load} />
    </div>
  );
}

function Field({ label, children }) {
  return <div><Label className="overline text-neutral-600">{label}</Label><div className="mt-1.5">{children}</div></div>;
}

function TrainingDetail({ training, onClose, onChange }) {
  const [history, setHistory] = useState([]);
  const [docs, setDocs] = useState([]);
  useEffect(() => {
    if (!training) return;
    setDocs(training.documents || []);
    api.get(`/trainings/${training.id}/history`).then(({ data }) => setHistory(data)).catch(() => {});
  }, [training]);
  if (!training) return null;
  const canUpload = training.activity_type === "externe_bijscholing" || training.activity_type === "overige_activiteit";

  const submitConcept = async () => {
    await api.post(`/trainings/${training.id}/submit`);
    toast.success("Ingediend"); onChange(); onClose();
  };

  return (
    <Sheet open={!!training} onOpenChange={(o) => !o && onClose()}>
      <SheetContent className="w-full sm:max-w-md overflow-y-auto" data-testid="training-detail">
        <SheetHeader><SheetTitle className="font-heading text-forest">{training.subject}</SheetTitle></SheetHeader>
        <div className="mt-4 space-y-5">
          <StatusBadge status={training.status} />
          <div className="grid grid-cols-2 gap-3 text-sm">
            <Info2 label="Datum" value={formatDate(training.date)} />
            <Info2 label="Uren" value={training.hours} />
            <Info2 label="Organisatie" value={training.organization} />
            <Info2 label="Spreker" value={training.speaker} />
            <Info2 label="Type" value={ACTIVITY_LABEL[training.activity_type]} />
            <Info2 label="Punten" value={training.points != null ? training.points : "—"} />
          </div>
          {training.content_explanation && <Info2 label="Inhoud / leerdoel" value={training.content_explanation} />}
          {training.admin_remark && (
            <div className="bg-orange-50 rounded-md p-3 text-sm text-orange-800" data-testid="admin-remark">
              <div className="overline text-orange-700 mb-1">Opmerking administratie</div>
              {training.admin_remark}
            </div>
          )}

          <div>
            <div className="overline text-neutral-600 mb-2">Deelnamebewijzen</div>
            <div className="flex flex-wrap gap-2 mb-3">
              {docs.map((d) => <DocumentChip key={d.id} doc={d} />)}
              {docs.length === 0 && <span className="text-sm text-neutral-400">Geen documenten</span>}
            </div>
            {canUpload && ["concept", "ingediend", "aanpassing_gevraagd", "in_beoordeling"].includes(training.status) && (
              <DocumentUpload compact trainingId={training.id}
                onUploaded={(d) => setDocs((p) => [...p, d])} />
            )}
          </div>

          {(training.status === "concept" || training.status === "aanpassing_gevraagd") && (
            <Button className="w-full bg-forest hover:bg-forest-hover text-white" data-testid="detail-submit-button" onClick={submitConcept}>
              Indienen ter beoordeling
            </Button>
          )}

          <div>
            <div className="flex items-center gap-1.5 overline text-neutral-600 mb-2"><History className="h-3.5 w-3.5" /> Historie</div>
            <Timeline items={history} />
          </div>
        </div>
      </SheetContent>
    </Sheet>
  );
}

function Info2({ label, value }) {
  return <div><div className="overline text-neutral-500">{label}</div><div className="text-sm text-neutral-800">{value || "—"}</div></div>;
}
