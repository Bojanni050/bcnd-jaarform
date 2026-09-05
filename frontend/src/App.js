import "@/App.css";
import { BrowserRouter, HashRouter, Routes, Route, Navigate } from "react-router-dom";
import { AuthProvider, useAuth } from "@/context/AuthContext";
import { AppLayout } from "@/components/AppLayout";
import { Toaster } from "@/components/ui/sonner";
import { Loader2, Leaf } from "lucide-react";
import { IS_WP } from "@/lib/api";

import Login from "@/pages/Login";
import MemberDashboard from "@/pages/member/MemberDashboard";
import Trainings from "@/pages/member/Trainings";
import Consults from "@/pages/member/Consults";
import AnnualForm from "@/pages/member/AnnualForm";
import Profile from "@/pages/member/Profile";
import AdminDashboard from "@/pages/admin/AdminDashboard";
import AdminTrainings from "@/pages/admin/AdminTrainings";
import AdminAnnualForms from "@/pages/admin/AdminAnnualForms";
import AdminMembers from "@/pages/admin/AdminMembers";
import AdminSettings from "@/pages/admin/AdminSettings";

const Router = IS_WP ? HashRouter : BrowserRouter;

function Loading() {
  return (
    <div className="min-h-[60vh] flex items-center justify-center bg-bone">
      <Loader2 className="h-6 w-6 animate-spin text-forest" />
    </div>
  );
}

function LoginPrompt() {
  return (
    <div className="min-h-[70vh] flex items-center justify-center bg-bone p-6">
      <div className="max-w-sm w-full text-center bg-white border border-neutral-200 rounded-xl p-8">
        <div className="h-12 w-12 rounded-xl bg-forest flex items-center justify-center mx-auto mb-4">
          <Leaf className="h-6 w-6 text-white" />
        </div>
        <h2 className="font-heading text-2xl font-semibold text-forest">BCND Ledenportaal</h2>
        <p className="text-sm text-neutral-500 mt-2 mb-6">Log in met uw account om uw bijscholingen en jaarformulier te beheren.</p>
        <a href={IS_WP ? window.BCND.loginUrl : "/login"}
          className="inline-flex items-center justify-center w-full rounded-md bg-forest hover:bg-forest-hover text-white px-4 py-2.5 text-sm transition-colors"
          data-testid="wp-login-link">
          Inloggen
        </a>
      </div>
    </div>
  );
}

function Protected({ role, children }) {
  const { user } = useAuth();
  if (user === null) return <Loading />;
  if (!user) return IS_WP ? <LoginPrompt /> : <Navigate to="/login" replace />;
  if (role === "admin" && user.role !== "admin") return <Navigate to="/" replace />;
  // An admin who is *also* a licensed member (has their own member_id) may still
  // use the member-facing pages for their own bijscholingen/jaarformulier.
  if (role === "member" && user.role === "admin" && !user.member_id) return <Navigate to="/admin" replace />;
  return <AppLayout>{children}</AppLayout>;
}

function LoginRoute() {
  const { user } = useAuth();
  if (user === null) return <Loading />;
  if (user) return <Navigate to={user.role === "admin" ? "/admin" : "/"} replace />;
  return IS_WP ? <LoginPrompt /> : <Login />;
}

function App() {
  return (
    <div className="App">
      <Router>
        <AuthProvider>
          <Toaster position="top-right" richColors />
          <Routes>
            <Route path="/login" element={<LoginRoute />} />

            <Route path="/" element={<Protected role="member"><MemberDashboard /></Protected>} />
            <Route path="/bijscholingen" element={<Protected role="member"><Trainings /></Protected>} />
            <Route path="/consulten" element={<Protected role="member"><Consults /></Protected>} />
            <Route path="/jaarformulier" element={<Protected role="member"><AnnualForm /></Protected>} />
            <Route path="/profiel" element={<Protected role="member"><Profile /></Protected>} />

            <Route path="/admin" element={<Protected role="admin"><AdminDashboard /></Protected>} />
            <Route path="/admin/bijscholingen" element={<Protected role="admin"><AdminTrainings /></Protected>} />
            <Route path="/admin/jaarformulieren" element={<Protected role="admin"><AdminAnnualForms /></Protected>} />
            <Route path="/admin/leden" element={<Protected role="admin"><AdminMembers /></Protected>} />
            <Route path="/admin/instellingen" element={<Protected role="admin"><AdminSettings /></Protected>} />

            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </AuthProvider>
      </Router>
    </div>
  );
}

export default App;
