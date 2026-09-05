import { useEffect, useState } from "react";
import api from "@/lib/api";
import { formatDate } from "@/components/Timeline";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { toast } from "sonner";
import { Loader2, User } from "lucide-react";

export default function Profile() {
  const [m, setM] = useState(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => { api.get("/members/me").then(({ data }) => setM(data)).catch(() => {}); }, []);
  if (!m) return <div className="py-20 flex justify-center"><Loader2 className="h-6 w-6 animate-spin text-forest" /></div>;

  const save = async () => {
    setSaving(true);
    try {
      await api.put("/members/me", {
        street: m.street, house_number: m.house_number, city: m.city, postal_code: m.postal_code, phone: m.phone,
      });
      toast.success("Profiel bijgewerkt");
    } catch (e) { toast.error(e.response?.data?.detail || "Opslaan mislukt"); }
    finally { setSaving(false); }
  };

  return (
    <div className="space-y-6 max-w-2xl" data-testid="profile-page">
      <div>
        <p className="overline text-terracotta">Account</p>
        <h1 className="font-heading text-3xl font-semibold tracking-tight text-forest mt-1">Mijn profiel</h1>
      </div>

      <Card className="p-6 border-neutral-200">
        <div className="flex items-center gap-4 mb-6">
          <div className="h-14 w-14 rounded-full bg-forest flex items-center justify-center text-white">
            <User className="h-7 w-7" />
          </div>
          <div>
            <div className="font-heading text-lg font-semibold text-forest">{m.name}</div>
            <div className="text-sm text-neutral-500">{m.email}</div>
          </div>
        </div>

        <div className="grid md:grid-cols-2 gap-4">
          <ReadOnly label="Lidnummer BCND" value={m.member_number} />
          <ReadOnly label="Licentielid sinds" value={formatDate(m.license_since)} />
          <ReadOnly label="Accountstatus" value={m.status === "active" ? "Actief" : m.status} />
          <ReadOnly label="Naam" value={m.name} />
          <Editable label="Straat" value={m.street} onChange={(v) => setM({ ...m, street: v })} testid="profile-street" />
          <Editable label="Huisnummer" value={m.house_number} onChange={(v) => setM({ ...m, house_number: v })} testid="profile-house-number" />
          <Editable label="Postcode" value={m.postal_code} onChange={(v) => setM({ ...m, postal_code: v })} testid="profile-postal" />
          <Editable label="Plaats" value={m.city} onChange={(v) => setM({ ...m, city: v })} testid="profile-city" />
          <Editable label="Telefoon" value={m.phone} onChange={(v) => setM({ ...m, phone: v })} testid="profile-phone" />
        </div>
        <p className="text-xs text-neutral-400 mt-4">
          Lidnummer, licentiedatum en naam kunnen alleen door de BCND-administratie worden gewijzigd.
        </p>
        <Button className="bg-forest hover:bg-forest-hover text-white mt-5" data-testid="save-profile-button" disabled={saving} onClick={save}>
          {saving && <Loader2 className="h-4 w-4 mr-2 animate-spin" />} Wijzigingen opslaan
        </Button>
      </Card>
    </div>
  );
}

function ReadOnly({ label, value }) {
  return (
    <div>
      <Label className="overline text-neutral-600">{label}</Label>
      <div className="mt-1.5 rounded-md bg-neutral-100 px-3 py-2 text-sm text-neutral-700">{value || "—"}</div>
    </div>
  );
}
function Editable({ label, value, onChange, testid }) {
  return (
    <div>
      <Label className="overline text-neutral-600">{label}</Label>
      <Input className="mt-1.5" data-testid={testid} value={value || ""} onChange={(e) => onChange(e.target.value)} />
    </div>
  );
}
