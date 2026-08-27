import { useEffect, useState } from "react";
import api from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { toast } from "sonner";
import { Stethoscope, Loader2 } from "lucide-react";

const YEAR = new Date().getFullYear();

export default function Consults() {
  const [year, setYear] = useState(YEAR);
  const [rec, setRec] = useState(null);
  const [norm, setNorm] = useState(null);
  const [saving, setSaving] = useState(false);

  const load = (y) => {
    api.get(`/consults?year=${y}`).then(({ data }) => setRec(data)).catch(() => {});
    api.get(`/annual-forms/overview?year=${y}`).then(({ data }) => setNorm(data.consults)).catch(() => {});
  };
  useEffect(() => { load(year); }, [year]);

  const save = async () => {
    setSaving(true);
    try {
      const { data } = await api.put(`/consults`, {
        year: parseInt(year),
        total_consults: parseInt(rec.total_consults) || 0,
        first_consults: parseInt(rec.first_consults) || 0,
        followup_consults: parseInt(rec.followup_consults) || 0,
        other_activities: rec.other_activities || "",
      });
      setRec(data);
      load(year);
      toast.success("Consulten opgeslagen");
    } catch (e) { toast.error(e.response?.data?.detail || "Opslaan mislukt"); }
    finally { setSaving(false); }
  };

  if (!rec) return <div className="py-20 flex justify-center"><Loader2 className="h-6 w-6 animate-spin text-forest" /></div>;
  const autoTotal = (parseInt(rec.first_consults) || 0) + (parseInt(rec.followup_consults) || 0);

  return (
    <div className="space-y-6 max-w-3xl" data-testid="consults-page">
      <div className="flex items-center justify-between">
        <div>
          <p className="overline text-terracotta">Registratie</p>
          <h1 className="font-heading text-3xl font-semibold tracking-tight text-forest mt-1">Consulten {year}</h1>
        </div>
        <Input type="number" className="w-28" data-testid="consult-year" value={year}
          onChange={(e) => setYear(e.target.value)} />
      </div>

      {norm && (
        <Card className={`p-5 border-neutral-200 ${norm.complete ? "bg-green-50/50" : "bg-neutral-100"}`}>
          <div className="flex items-center gap-4">
            <div className={`h-11 w-11 rounded-xl flex items-center justify-center ${norm.complete ? "bg-forest" : "bg-terracotta"}`}>
              <Stethoscope className="h-5 w-5 text-white" />
            </div>
            <div>
              <div className="font-heading text-xl font-semibold text-forest" data-testid="consult-norm">
                {norm.achieved} / {norm.required} consulten
              </div>
              <div className="text-sm text-neutral-500">
                {norm.complete ? "Norm behaald" : `Nog ${norm.remaining} consult(en) nodig`}
              </div>
            </div>
          </div>
        </Card>
      )}

      <Card className="p-6 border-neutral-200 space-y-4">
        <div className="grid md:grid-cols-2 gap-4">
          <div>
            <Label className="overline text-neutral-600">Aantal 1e consulten</Label>
            <Input type="number" className="mt-1.5" data-testid="first-consults" value={rec.first_consults}
              onChange={(e) => setRec({ ...rec, first_consults: e.target.value, total_consults: "" })} />
          </div>
          <div>
            <Label className="overline text-neutral-600">Aantal vervolgconsulten</Label>
            <Input type="number" className="mt-1.5" data-testid="followup-consults" value={rec.followup_consults}
              onChange={(e) => setRec({ ...rec, followup_consults: e.target.value, total_consults: "" })} />
          </div>
        </div>
        <div className="bg-neutral-50 rounded-md p-3 flex items-center justify-between">
          <span className="text-sm text-neutral-600">Totaal aantal consulten (automatisch berekend)</span>
          <span className="font-heading text-lg font-semibold text-forest" data-testid="total-consults">{autoTotal}</span>
        </div>
        <div>
          <Label className="overline text-neutral-600">Overige activiteiten (lezingen, workshops, e.d.)</Label>
          <Textarea className="mt-1.5" rows={2} data-testid="other-activities" value={rec.other_activities}
            onChange={(e) => setRec({ ...rec, other_activities: e.target.value })} />
        </div>
        <Button className="bg-forest hover:bg-forest-hover text-white" data-testid="save-consults-button"
          disabled={saving} onClick={save}>
          {saving && <Loader2 className="h-4 w-4 mr-2 animate-spin" />} Opslaan
        </Button>
      </Card>
    </div>
  );
}
