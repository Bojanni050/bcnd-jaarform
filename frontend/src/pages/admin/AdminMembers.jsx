import { useEffect, useState, useCallback } from "react";
import api from "@/lib/api";
import { formatDate } from "@/components/Timeline";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogTrigger,
} from "@/components/ui/dialog";
import { toast } from "sonner";
import { Loader2, Users, Plus, UserPlus } from "lucide-react";

const emptyNew = {
  name: "", email: "", password: "", member_number: "", license_since: "",
  street: "", house_number: "", postal_code: "", city: "", phone: "", status: "active",
};

export default function AdminMembers() {
  const [items, setItems] = useState([]);
  const [q, setQ] = useState("");
  const [edit, setEdit] = useState(null);
  const [newOpen, setNewOpen] = useState(false);
  const [nm, setNm] = useState(emptyNew);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    api.get(`/members?q=${encodeURIComponent(q)}`).then(({ data }) => setItems(data)).catch(() => {});
  }, [q]);
  useEffect(() => { load(); }, [load]);

  const create = async () => {
    if (!nm.name || !nm.email || !nm.password || !nm.license_since) { toast.error("Naam, e-mail, wachtwoord en licentiedatum verplicht"); return; }
    setBusy(true);
    try {
      await api.post("/members", nm);
      toast.success("Lid aangemaakt"); setNewOpen(false); setNm(emptyNew); load();
    } catch (e) { toast.error(e.response?.data?.detail || "Mislukt"); }
    finally { setBusy(false); }
  };

  const saveEdit = async () => {
    setBusy(true);
    try {
      await api.put(`/members/${edit.id}`, {
        name: edit.name, street: edit.street, house_number: edit.house_number,
        city: edit.city, postal_code: edit.postal_code,
        member_number: edit.member_number, license_since: edit.license_since, phone: edit.phone,
        status: edit.status, notes: edit.notes,
      });
      toast.success("Lid bijgewerkt"); setEdit(null); load();
    } catch (e) { toast.error(e.response?.data?.detail || "Mislukt"); }
    finally { setBusy(false); }
  };

  return (
    <div className="space-y-6" data-testid="admin-members">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <p className="overline text-terracotta">Beheer</p>
          <h1 className="font-heading text-3xl font-semibold tracking-tight text-forest mt-1">Leden</h1>
        </div>
        <div className="flex gap-3">
          <Input placeholder="Zoek op naam, e-mail of lidnummer…" className="w-64" data-testid="member-search"
            value={q} onChange={(e) => setQ(e.target.value)} />
          <Dialog open={newOpen} onOpenChange={setNewOpen}>
            <DialogTrigger asChild>
              <Button className="bg-forest hover:bg-forest-hover text-white" data-testid="new-member-button">
                <Plus className="h-4 w-4 mr-1.5" /> Nieuw lid
              </Button>
            </DialogTrigger>
            <DialogContent className="max-w-lg max-h-[90vh] overflow-y-auto">
              <DialogHeader><DialogTitle className="font-heading text-forest">Nieuw licentielid</DialogTitle></DialogHeader>
              <div className="grid grid-cols-2 gap-3 py-2">
                <F label="Naam *"><Input data-testid="nm-name" value={nm.name} onChange={(e) => setNm({ ...nm, name: e.target.value })} /></F>
                <F label="E-mail *"><Input type="email" data-testid="nm-email" value={nm.email} onChange={(e) => setNm({ ...nm, email: e.target.value })} /></F>
                <F label="Wachtwoord *"><Input data-testid="nm-password" value={nm.password} onChange={(e) => setNm({ ...nm, password: e.target.value })} /></F>
                <F label="Lidnummer BCND"><Input data-testid="nm-number" value={nm.member_number} onChange={(e) => setNm({ ...nm, member_number: e.target.value })} /></F>
                <F label="Licentielid sinds *"><Input type="date" data-testid="nm-license" value={nm.license_since} onChange={(e) => setNm({ ...nm, license_since: e.target.value })} /></F>
                <F label="Telefoon"><Input data-testid="nm-phone" value={nm.phone} onChange={(e) => setNm({ ...nm, phone: e.target.value })} /></F>
                <F label="Straat"><Input data-testid="nm-street" value={nm.street} onChange={(e) => setNm({ ...nm, street: e.target.value })} /></F>
                <F label="Huisnummer"><Input data-testid="nm-house-number" value={nm.house_number} onChange={(e) => setNm({ ...nm, house_number: e.target.value })} /></F>
                <F label="Postcode"><Input data-testid="nm-postal" value={nm.postal_code} onChange={(e) => setNm({ ...nm, postal_code: e.target.value })} /></F>
                <F label="Plaats"><Input data-testid="nm-city" value={nm.city} onChange={(e) => setNm({ ...nm, city: e.target.value })} /></F>
              </div>
              <DialogFooter>
                <Button variant="outline" onClick={() => setNewOpen(false)}>Annuleren</Button>
                <Button className="bg-forest hover:bg-forest-hover text-white" data-testid="create-member-button" disabled={busy} onClick={create}>
                  <UserPlus className="h-4 w-4 mr-2" /> Aanmaken
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </div>
      </div>

      <Card className="border-neutral-200 overflow-hidden">
        {items.length === 0 ? (
          <div className="px-6 py-16 text-center text-neutral-400"><Users className="h-8 w-8 mx-auto mb-2 opacity-40" /> Geen leden gevonden.</div>
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 text-neutral-500 text-left">
              <tr>
                <th className="px-5 py-3 font-medium">Naam</th>
                <th className="px-3 py-3 font-medium">Lidnummer</th>
                <th className="px-3 py-3 font-medium">Sinds</th>
                <th className="px-3 py-3 font-medium">Plaats</th>
                <th className="px-3 py-3 font-medium">Status</th>
                <th className="px-5 py-3"></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {items.map((m) => (
                <tr key={m.id} className="hover:bg-neutral-50 transition-colors" data-testid={`member-row-${m.id}`}>
                  <td className="px-5 py-3 font-medium text-neutral-800">{m.name}<div className="text-xs text-neutral-400 font-normal">{m.email}</div></td>
                  <td className="px-3 py-3 text-neutral-600">{m.member_number || "—"}</td>
                  <td className="px-3 py-3 text-neutral-600">{formatDate(m.license_since)}</td>
                  <td className="px-3 py-3 text-neutral-600">{m.city || "—"}</td>
                  <td className="px-3 py-3">
                    <span className={`text-xs font-medium ${m.status === "active" ? "text-green-700" : "text-neutral-500"}`}>
                      {m.status === "active" ? "Actief" : m.status}
                    </span>
                  </td>
                  <td className="px-5 py-3 text-right">
                    <Button size="sm" variant="outline" data-testid={`edit-member-${m.id}`} onClick={() => setEdit({ ...m })}>Bewerken</Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>

      <Sheet open={!!edit} onOpenChange={(o) => !o && setEdit(null)}>
        <SheetContent className="w-full sm:max-w-md overflow-y-auto" data-testid="edit-member-sheet">
          <SheetHeader><SheetTitle className="font-heading text-forest">Lid bewerken</SheetTitle></SheetHeader>
          {edit && (
            <div className="mt-4 space-y-3">
              <F label="Naam"><Input data-testid="em-name" value={edit.name || ""} onChange={(e) => setEdit({ ...edit, name: e.target.value })} /></F>
              <F label="Lidnummer BCND"><Input data-testid="em-number" value={edit.member_number || ""} onChange={(e) => setEdit({ ...edit, member_number: e.target.value })} /></F>
              <F label="Licentielid sinds"><Input type="date" data-testid="em-license" value={(edit.license_since || "").slice(0, 10)} onChange={(e) => setEdit({ ...edit, license_since: e.target.value })} /></F>
              <div className="grid grid-cols-2 gap-3">
                <F label="Straat"><Input value={edit.street || ""} onChange={(e) => setEdit({ ...edit, street: e.target.value })} /></F>
                <F label="Huisnummer"><Input value={edit.house_number || ""} onChange={(e) => setEdit({ ...edit, house_number: e.target.value })} /></F>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <F label="Postcode"><Input value={edit.postal_code || ""} onChange={(e) => setEdit({ ...edit, postal_code: e.target.value })} /></F>
                <F label="Plaats"><Input value={edit.city || ""} onChange={(e) => setEdit({ ...edit, city: e.target.value })} /></F>
              </div>
              <F label="Telefoon"><Input value={edit.phone || ""} onChange={(e) => setEdit({ ...edit, phone: e.target.value })} /></F>
              <F label="Status"><Input value={edit.status || ""} onChange={(e) => setEdit({ ...edit, status: e.target.value })} /></F>
              <Button className="w-full bg-forest hover:bg-forest-hover text-white" data-testid="save-member-button" disabled={busy} onClick={saveEdit}>
                {busy && <Loader2 className="h-4 w-4 mr-2 animate-spin" />} Opslaan
              </Button>
            </div>
          )}
        </SheetContent>
      </Sheet>
    </div>
  );
}

function F({ label, children }) {
  return <div><Label className="overline text-neutral-600">{label}</Label><div className="mt-1.5">{children}</div></div>;
}
