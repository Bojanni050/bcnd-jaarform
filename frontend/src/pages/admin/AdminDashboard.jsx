import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "@/lib/api";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import {
  ClipboardCheck, FileText, AlertTriangle, CalendarClock, Users,
  FileWarning, ArrowRight, Loader2, CheckCircle2,
} from "lucide-react";

const YEAR = new Date().getFullYear();

export default function AdminDashboard() {
  const navigate = useNavigate();
  const [d, setD] = useState(null);
  useEffect(() => { api.get(`/admin/dashboard?year=${YEAR}`).then(({ data }) => setD(data)).catch(() => {}); }, []);
  if (!d) return <div className="py-20 flex justify-center"><Loader2 className="h-6 w-6 animate-spin text-forest" /></div>;

  return (
    <div className="space-y-6" data-testid="admin-dashboard">
      <div>
        <p className="overline text-terracotta">Beheeromgeving · {YEAR}</p>
        <h1 className="font-heading text-3xl md:text-4xl font-semibold tracking-tight text-forest mt-1">Wat moet ik beoordelen?</h1>
      </div>

      {/* Priority action banner */}
      <div className="grid md:grid-cols-2 gap-5">
        <QueueCard title="Bijscholingen ter beoordeling" count={d.trainings_pending + d.trainings_in_review}
          icon={ClipboardCheck} accent onClick={() => navigate("/admin/bijscholingen")} testid="queue-trainings" />
        <QueueCard title="Jaarformulieren ter controle" count={d.forms_to_review}
          icon={FileText} accent onClick={() => navigate("/admin/jaarformulieren")} testid="queue-forms" />
      </div>

      {/* Metrics */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <Metric icon={ClipboardCheck} label="Openstaand" value={d.trainings_pending} />
        <Metric icon={AlertTriangle} label="Aanpassing gevraagd" value={d.trainings_changes} />
        <Metric icon={CheckCircle2} label="Formulieren goedgekeurd" value={d.forms_approved} />
        <Metric icon={CalendarClock} label="Dagen tot deadline" value={d.days_until_deadline >= 0 ? d.days_until_deadline : 0} />
      </div>

      <div className="grid md:grid-cols-3 gap-5">
        <ListCard title="Leden met achterstand" icon={AlertTriangle} testid="behind-list"
          items={d.members_behind.map((m) => ({
            id: m.member_id, name: m.name,
            sub: `${m.points.achieved}/${m.points.required} pt · ${m.consults.achieved}/${m.consults.required} consulten`,
          }))} empty="Alle leden op schema" onClick={() => navigate("/admin/leden")} />
        <ListCard title="Deadline nadert" icon={CalendarClock} testid="deadline-list"
          items={d.members_deadline_soon.map((m) => ({ id: m.member_id, name: m.name, sub: `nog ${m.days} dagen` }))}
          empty="Geen urgente deadlines" onClick={() => navigate("/admin/leden")} />
        <ListCard title="Ontbrekende documenten" icon={FileWarning} testid="missing-docs-list"
          items={d.members_missing_docs.map((m) => ({ id: m.member_id, name: m.name, sub: `${m.count} ontbrekend` }))}
          empty="Geen ontbrekende documenten" onClick={() => navigate("/admin/bijscholingen")} />
      </div>
    </div>
  );
}

function QueueCard({ title, count, icon: Icon, onClick, accent, testid }) {
  return (
    <button onClick={onClick} data-testid={testid}
      className="text-left rounded-xl border border-neutral-200 bg-white p-6 hover:shadow-md transition-shadow group">
      <div className="flex items-start justify-between">
        <div className={`h-11 w-11 rounded-xl flex items-center justify-center ${accent ? "bg-terracotta" : "bg-forest"}`}>
          <Icon className="h-5 w-5 text-white" />
        </div>
        <span className="font-heading text-4xl font-semibold text-forest">{count}</span>
      </div>
      <div className="mt-4 flex items-center justify-between">
        <span className="font-medium text-neutral-800">{title}</span>
        <ArrowRight className="h-4 w-4 text-neutral-400 group-hover:translate-x-1 transition-transform" />
      </div>
    </button>
  );
}

function Metric({ icon: Icon, label, value }) {
  return (
    <Card className="p-5 border-neutral-200">
      <Icon className="h-5 w-5 text-sage mb-3" />
      <div className="font-heading text-2xl font-semibold text-forest">{value}</div>
      <div className="text-xs text-neutral-500 mt-0.5">{label}</div>
    </Card>
  );
}

function ListCard({ title, icon: Icon, items, empty, onClick, testid }) {
  return (
    <Card className="border-neutral-200" data-testid={testid}>
      <div className="px-5 py-4 border-b border-neutral-100 flex items-center gap-2">
        <Icon className="h-4 w-4 text-terracotta" />
        <span className="font-heading font-semibold text-sm text-forest">{title}</span>
        <span className="ml-auto text-xs text-neutral-400">{items.length}</span>
      </div>
      <div className="max-h-64 overflow-auto">
        {items.length === 0 ? <div className="px-5 py-8 text-center text-sm text-neutral-400">{empty}</div> : (
          items.map((it) => (
            <button key={it.id} onClick={onClick}
              className="w-full text-left px-5 py-3 border-b border-neutral-50 hover:bg-neutral-50 transition-colors">
              <div className="text-sm font-medium text-neutral-800">{it.name}</div>
              <div className="text-xs text-neutral-500">{it.sub}</div>
            </button>
          ))
        )}
      </div>
    </Card>
  );
}
