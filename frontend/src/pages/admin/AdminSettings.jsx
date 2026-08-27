import { useEffect, useState } from "react";
import api from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Switch } from "@/components/ui/switch";
import { toast } from "sonner";
import { Loader2, Save, Settings as SettingsIcon } from "lucide-react";

export default function AdminSettings() {
  const [s, setS] = useState(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => { api.get("/settings").then(({ data }) => setS(data)).catch(() => {}); }, []);
  if (!s) return <div className="py-20 flex justify-center"><Loader2 className="h-6 w-6 animate-spin text-forest" /></div>;

  const setNorm = (k, v) => setS({ ...s, consults_norms: { ...s.consults_norms, [k]: parseInt(v) || 0 } });
  const setTpl = (k, v) => setS({ ...s, email_templates: { ...s.email_templates, [k]: v } });

  const save = async () => {
    setBusy(true);
    try {
      await api.put("/settings", {
        points_norm: parseInt(s.points_norm),
        consults_norms: s.consults_norms,
        deadline_day: parseInt(s.deadline_day),
        deadline_month: parseInt(s.deadline_month),
        notifications_enabled: s.notifications_enabled,
        email_templates: s.email_templates,
      });
      toast.success("Instellingen opgeslagen");
    } catch (e) { toast.error(e.response?.data?.detail || "Mislukt"); }
    finally { setBusy(false); }
  };

  return (
    <div className="space-y-6 max-w-3xl" data-testid="admin-settings">
      <div>
        <p className="overline text-terracotta">Configuratie</p>
        <h1 className="font-heading text-3xl font-semibold tracking-tight text-forest mt-1">Instellingen</h1>
      </div>

      <Card className="p-6 border-neutral-200 space-y-4">
        <h3 className="font-heading font-semibold text-forest flex items-center gap-2"><SettingsIcon className="h-4 w-4" /> Normen</h3>
        <div className="grid md:grid-cols-2 gap-4">
          <div>
            <Label className="overline text-neutral-600">Bijscholingspunten per jaar</Label>
            <Input type="number" className="mt-1.5" data-testid="points-norm" value={s.points_norm}
              onChange={(e) => setS({ ...s, points_norm: e.target.value })} />
          </div>
        </div>
        <div>
          <Label className="overline text-neutral-600">Consultennorm per lidmaatschapsjaar</Label>
          <div className="grid grid-cols-4 gap-3 mt-1.5">
            {["1", "2", "3", "4"].map((y) => (
              <div key={y}>
                <div className="text-xs text-neutral-500 mb-1">{y === "4" ? "Jaar 4+" : `Jaar ${y}`}</div>
                <Input type="number" data-testid={`consult-norm-${y}`} value={s.consults_norms[y] ?? ""}
                  onChange={(e) => setNorm(y, e.target.value)} />
              </div>
            ))}
          </div>
        </div>
      </Card>

      <Card className="p-6 border-neutral-200 space-y-4">
        <h3 className="font-heading font-semibold text-forest">Deadline</h3>
        <div className="grid grid-cols-2 gap-4 max-w-sm">
          <div>
            <Label className="overline text-neutral-600">Dag</Label>
            <Input type="number" className="mt-1.5" data-testid="deadline-day" value={s.deadline_day}
              onChange={(e) => setS({ ...s, deadline_day: e.target.value })} />
          </div>
          <div>
            <Label className="overline text-neutral-600">Maand</Label>
            <Input type="number" className="mt-1.5" data-testid="deadline-month" value={s.deadline_month}
              onChange={(e) => setS({ ...s, deadline_month: e.target.value })} />
          </div>
        </div>
        <div className="flex items-center justify-between pt-2">
          <div>
            <div className="text-sm font-medium text-neutral-800">Notificaties inschakelen</div>
            <div className="text-xs text-neutral-500">Herinneringen en statusmeldingen voor leden</div>
          </div>
          <Switch data-testid="notifications-toggle" checked={s.notifications_enabled}
            onCheckedChange={(v) => setS({ ...s, notifications_enabled: v })} />
        </div>
      </Card>

      <Card className="p-6 border-neutral-200 space-y-4">
        <h3 className="font-heading font-semibold text-forest">E-mailteksten (configureerbaar)</h3>
        <p className="text-xs text-neutral-500">Gebruik variabelen zoals {"{name}"}, {"{subject}"}, {"{points}"}, {"{year}"}, {"{remark}"}, {"{days}"}.</p>
        {Object.entries(s.email_templates || {}).map(([k, v]) => (
          <div key={k}>
            <Label className="overline text-neutral-600">{k.replace(/_/g, " ")}</Label>
            <Textarea className="mt-1.5" rows={2} data-testid={`tpl-${k}`} value={v} onChange={(e) => setTpl(k, e.target.value)} />
          </div>
        ))}
      </Card>

      <Button className="bg-forest hover:bg-forest-hover text-white" data-testid="save-settings-button" disabled={busy} onClick={save}>
        {busy ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <Save className="h-4 w-4 mr-2" />} Instellingen opslaan
      </Button>
    </div>
  );
}
