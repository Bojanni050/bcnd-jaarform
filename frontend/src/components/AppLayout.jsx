import { useEffect, useState } from "react";
import { NavLink, useNavigate, useLocation, Link } from "react-router-dom";
import { useAuth } from "@/context/AuthContext";
import api, { IS_WP } from "@/lib/api";
import {
  LayoutDashboard, GraduationCap, Stethoscope, FileText, User, LogOut,
  Users, ClipboardCheck, Settings as SettingsIcon, Bell, Leaf, ShieldCheck, ArrowLeftRight,
} from "lucide-react";
import { Button } from "@/components/ui/button";

const memberNav = [
  { to: "/", label: "Dashboard", icon: LayoutDashboard, end: true },
  { to: "/bijscholingen", label: "Bijscholingen", icon: GraduationCap },
  { to: "/consulten", label: "Consulten", icon: Stethoscope },
  { to: "/jaarformulier", label: "Jaarformulier", icon: FileText },
  { to: "/profiel", label: "Mijn profiel", icon: User },
];

const adminNav = [
  { to: "/admin", label: "Dashboard", icon: LayoutDashboard, end: true },
  { to: "/admin/bijscholingen", label: "Bijscholingen", icon: ClipboardCheck },
  { to: "/admin/jaarformulieren", label: "Jaarformulieren", icon: FileText },
  { to: "/admin/leden", label: "Leden", icon: Users },
  { to: "/admin/instellingen", label: "Instellingen", icon: SettingsIcon },
];

export function AppLayout({ children }) {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  // Which side of the app we're showing follows the URL, not just the role,
  // so an admin who is also a licensed member can be on either side.
  const onAdminSide = location.pathname.startsWith("/admin");
  const isAdmin = user?.role === "admin";
  const isAlsoMember = isAdmin && !!user?.member_id;
  const nav = onAdminSide ? adminNav : memberNav;
  const [unread, setUnread] = useState(0);
  const [open, setOpen] = useState(false);

  useEffect(() => {
    const load = () => api.get("/notifications").then(({ data }) => setUnread(data.unread)).catch(() => {});
    load();
    const t = setInterval(load, 30000);
    return () => clearInterval(t);
  }, []);

  return (
    <div className="min-h-screen flex">
      {/* Sidebar */}
      <aside className={`w-64 shrink-0 bg-[#064413] text-white flex-col hidden md:flex ${IS_WP ? "md:sticky md:top-0 md:self-start md:h-screen" : "fixed h-screen"}`}>
        <div className="px-6 py-6 border-b border-white/10 flex items-center gap-2.5">
          <div className="h-9 w-9 rounded-lg bg-terracotta flex items-center justify-center">
            <Leaf className="h-5 w-5 text-white" />
          </div>
          <div>
            <div className="font-heading font-semibold leading-tight">BCND</div>
            <div className="text-[10px] text-white/50 leading-tight">Nascholingsadministratie</div>
          </div>
        </div>
        <nav className="flex-1 px-3 py-5 space-y-1">
          {nav.map((n) => (
            <NavLink
              key={n.to} to={n.to} end={n.end}
              data-testid={`nav-${n.label.toLowerCase().replace(/\s/g, "-")}`}
              className={({ isActive }) =>
                `flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors ${
                  isActive ? "bg-white/15 text-white font-medium" : "text-white/70 hover:bg-white/10 hover:text-white"
                }`
              }
            >
              <n.icon className="h-4 w-4" />
              {n.label}
            </NavLink>
          ))}
        </nav>
        <div className="px-4 py-4 border-t border-white/10">
          {onAdminSide && (
            <div className="flex items-center gap-1.5 text-[11px] text-white/50 mb-3">
              <ShieldCheck className="h-3.5 w-3.5" /> Administratie
            </div>
          )}
          {isAlsoMember && (
            <Link
              to={onAdminSide ? "/" : "/admin"}
              data-testid="switch-view-link"
              className="flex items-center gap-1.5 text-xs text-white/70 hover:text-white mb-3"
            >
              <ArrowLeftRight className="h-3.5 w-3.5" />
              {onAdminSide ? "Naar mijn ledenprofiel" : "Naar beheeromgeving"}
            </Link>
          )}
          <div className="text-sm font-medium truncate">{user?.name}</div>
          <div className="text-xs text-white/50 truncate mb-3">{user?.email}</div>
          <Button
            data-testid="logout-button" variant="outline" size="sm"
            onClick={async () => { await logout(); navigate("/login"); }}
            className="w-full bg-transparent border-white/20 text-white hover:bg-white/10 hover:text-white"
          >
            <LogOut className="h-4 w-4 mr-2" /> Uitloggen
          </Button>
        </div>
      </aside>

      {/* Main */}
      <div className={`flex-1 min-w-0 ${IS_WP ? "" : "md:ml-64"}`}>
        <header className="sticky top-0 z-20 bg-white border-b border-neutral-200 px-5 md:px-8 py-3.5 flex items-center justify-between">
          <div className="md:hidden font-heading font-semibold text-forest">BCND</div>
          <div className="hidden md:block text-sm text-neutral-500">
            {onAdminSide ? "Beheeromgeving" : "Ledenomgeving"}
          </div>
          <div className="relative">
            <button
              data-testid="notifications-button"
              onClick={() => { setOpen(!open); }}
              className="relative p-2 rounded-lg hover:bg-neutral-100 transition-colors"
            >
              <Bell className="h-5 w-5 text-neutral-600" />
              {unread > 0 && (
                <span className="absolute -top-0.5 -right-0.5 h-4 min-w-4 px-1 rounded-full bg-terracotta text-white text-[10px] flex items-center justify-center">
                  {unread}
                </span>
              )}
            </button>
            {open && <NotificationPanel onClose={() => setOpen(false)} onRead={() => setUnread(0)} />}
          </div>
        </header>
        <main className="p-5 md:p-8 max-w-[1400px]">{children}</main>
      </div>
    </div>
  );
}

function NotificationPanel({ onClose, onRead }) {
  const [items, setItems] = useState([]);
  useEffect(() => {
    api.get("/notifications").then(({ data }) => setItems(data.items)).catch(() => {});
  }, []);
  const markAll = async () => {
    await api.post("/notifications/read-all");
    setItems((p) => p.map((i) => ({ ...i, read: true })));
    onRead();
  };
  return (
    <>
      <div className="fixed inset-0 z-30" onClick={onClose} />
      <div className="absolute right-0 mt-2 w-80 max-h-96 overflow-auto bg-white border border-neutral-200 rounded-xl shadow-lg z-40" data-testid="notifications-panel">
        <div className="flex items-center justify-between px-4 py-3 border-b border-neutral-100">
          <span className="font-heading font-semibold text-sm">Notificaties</span>
          <button className="text-xs text-forest hover:underline" onClick={markAll} data-testid="mark-all-read">Alles gelezen</button>
        </div>
        {items.length === 0 && <div className="p-4 text-sm text-neutral-400">Geen notificaties</div>}
        {items.map((n) => (
          <div key={n.id} className={`px-4 py-3 border-b border-neutral-50 ${n.read ? "" : "bg-neutral-50"}`}>
            <div className="text-sm font-medium text-neutral-800">{n.title}</div>
            <div className="text-xs text-neutral-500 mt-0.5">{n.message}</div>
          </div>
        ))}
      </div>
    </>
  );
}
