import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "@/lib/api";
import { ProgressRing } from "@/components/ProgressRing";
import { StatusBadge } from "@/components/StatusBadge";
import { formatDate } from "@/components/Timeline";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import {
  GraduationCap, Stethoscope, CalendarClock, ArrowRight, CheckCircle2,
  AlertTriangle, Plus, FileText, Loader2,
} from "lucide-react";

const YEAR = new Date().getFullYear();

export default function MemberDashboard() {
  const navigate = useNavigate();
  const [ov, setOv] = useState(null);
  const [trainings, setTrainings] = useState([]);
  const [member, setMember] = useState(null);

  useEffect(() => {
    api.get(`/annual-forms/overview?year=${YEAR}`).then(({ data }) => setOv(data)).catch(() => {});
    api.get(`/trainings?year=${YEAR}`).then(({ data }) => setTrainings(data)).catch(() => {});
    api.get(`/members/me`).then(({ data }) => setMember(data)).catch(() => {});
  }, []);

  if (!ov) return <div className="py-20 flex justify-center"><Loader2 className="h-6 w-6 animate-spin text-forest" /></div>;

  const todos = [];
  if (ov.points.remaining > 0) todos.push(`${ov.points.remaining} bijscholingspunt(en)`);
  if (ov.consults.remaining > 0) todos.push(`${ov.consults.remaining} consult(en)`);
  if (ov.counts.missing_documents > 0) todos.push(`${ov.counts.missing_documents} ontbrekend deelnamebewijs`);

  return (
    <div className="space-y-6" data-testid="member-dashboard">
      <div>
        <p className="overline text-terracotta">Jaaroverzicht {YEAR}</p>
        <h1 className="font-heading text-3xl md:text-4xl font-semibold tracking-tight text-forest mt-1">
          Hallo {member?.name?.split(" ")[0] || ""}
        </h1>
      </div>

      {/* What do I still need to do */}
      <Card className={`p-6 border-neutral-200 ${ov.all_complete ? "bg-green-50/50" : "bg-neutral-100"}`} data-testid="todo-banner">
        <div className="flex flex-col md:flex-row md:items-center gap-5 justify-between">
          <div className="flex items-start gap-4">
            <div className={`h-12 w-12 rounded-xl flex items-center justify-center shrink-0 ${ov.all_complete ? "bg-forest" : "bg-terracotta"}`}>
              {ov.all_complete ? <CheckCircle2 className="h-6 w-6 text-white" /> : <AlertTriangle className="h-6 w-6 text-white" />}
            </div>
            <div>
              <h2 className="font-heading text-lg font-semibold text-forest">
                {ov.all_complete ? "Je voldoet aan alle normen 🎉" : "Wat moet ik nog doen?"}
              </h2>
              {ov.all_complete ? (
                <p className="text-sm text-neutral-600 mt-1">Je kunt je jaarformulier indienen.</p>
              ) : (
                <p className="text-sm text-neutral-600 mt-1">Nog nodig: {todos.join(" · ")}</p>
              )}
            </div>
          </div>
          <div className="flex gap-2 shrink-0">
            <Button data-testid="dash-add-training" onClick={() => navigate("/bijscholingen")}
              className="bg-forest hover:bg-forest-hover text-white">
              <Plus className="h-4 w-4 mr-1.5" /> Bijscholing toevoegen
            </Button>
            <Button data-testid="dash-update-consults" variant="outline" onClick={() => navigate("/consulten")}>
              Consulten bijwerken
            </Button>
          </div>
        </div>
      </Card>

      {/* Progress rings + deadline */}
      <div className="grid md:grid-cols-3 gap-5">
        <Card className="p-6 flex items-center justify-around border-neutral-200 md:col-span-2">
          <ProgressRing testid="ring-points" value={ov.points.achieved} max={ov.points.required}
            complete={ov.points.complete} label="Bijscholingspunten"
            sublabel={ov.points.required === 0 ? "geen norm" : "punten"} />
          <ProgressRing testid="ring-consults" value={ov.consults.achieved} max={ov.consults.required}
            complete={ov.consults.complete} label="Consulten" sublabel="consulten" />
        </Card>

        <Card className="p-6 border-neutral-200 flex flex-col justify-between">
          <div>
            <div className="flex items-center gap-2 text-neutral-500 mb-2">
              <CalendarClock className="h-4 w-4" />
              <span className="overline">Deadline</span>
            </div>
            <div className="font-heading text-4xl font-semibold text-forest">
              {ov.days_until_deadline >= 0 ? ov.days_until_deadline : 0}
            </div>
            <p className="text-sm text-neutral-500 mt-1">
              {ov.days_until_deadline >= 0
                ? `dagen om je jaarformulier ${YEAR} in te dienen`
                : "de deadline is verstreken"}
            </p>
          </div>
          <Button data-testid="dash-goto-form" variant="ghost" onClick={() => navigate("/jaarformulier")}
            className="justify-start px-0 text-forest hover:bg-transparent hover:underline mt-4">
            Naar jaarformulier <ArrowRight className="h-4 w-4 ml-1" />
          </Button>
        </Card>
      </div>

      {/* Stats + recent */}
      <div className="grid md:grid-cols-4 gap-5">
        <StatCard icon={GraduationCap} label="Bijscholingen" value={ov.counts.trainings_total} />
        <StatCard icon={CheckCircle2} label="Goedgekeurd" value={ov.counts.approved} />
        <StatCard icon={Stethoscope} label="Consulten" value={ov.consults.achieved} />
        <StatCard icon={AlertTriangle} label="Ontbrekende docs" value={ov.counts.missing_documents}
          alert={ov.counts.missing_documents > 0} />
      </div>

      <Card className="border-neutral-200">
        <div className="px-6 py-4 border-b border-neutral-100 flex items-center justify-between">
          <h3 className="font-heading font-semibold text-forest">Recente registraties</h3>
          <Button variant="ghost" size="sm" onClick={() => navigate("/bijscholingen")} className="text-forest">
            Alles bekijken <ArrowRight className="h-4 w-4 ml-1" />
          </Button>
        </div>
        {trainings.length === 0 ? (
          <div className="px-6 py-10 text-center text-neutral-400">
            <FileText className="h-8 w-8 mx-auto mb-2 opacity-40" />
            Nog geen bijscholingen geregistreerd.
          </div>
        ) : (
          <div className="divide-y divide-neutral-100">
            {trainings.slice(0, 5).map((t) => (
              <div key={t.id} className="px-6 py-3.5 flex items-center justify-between hover:bg-neutral-50 transition-colors">
                <div className="min-w-0">
                  <div className="font-medium text-neutral-800 truncate">{t.subject || "—"}</div>
                  <div className="text-xs text-neutral-500">{formatDate(t.date)} · {t.organization}</div>
                </div>
                <div className="flex items-center gap-3 shrink-0">
                  {t.points != null && <span className="text-sm text-forest font-medium">{t.points} pt</span>}
                  <StatusBadge status={t.status} />
                </div>
              </div>
            ))}
          </div>
        )}
      </Card>
    </div>
  );
}

function StatCard({ icon: Icon, label, value, alert }) {
  return (
    <Card className="p-5 border-neutral-200">
      <Icon className={`h-5 w-5 mb-3 ${alert ? "text-terracotta" : "text-sage"}`} />
      <div className="font-heading text-2xl font-semibold text-forest">{value}</div>
      <div className="text-xs text-neutral-500 mt-0.5">{label}</div>
    </Card>
  );
}
