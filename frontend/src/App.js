import "@/App.css";
import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import { AuthProvider, useAuth } from "@/context/AuthContext";
import { AppLayout } from "@/components/AppLayout";
import { Toaster } from "@/components/ui/sonner";
import { Loader2 } from "lucide-react";

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

function Loading() {
  return (
    <div className="min-h-screen flex items-center justify-center bg-bone">
      <Loader2 className="h-6 w-6 animate-spin text-forest" />
    </div>
  );
}

function Protected({ role, children }) {
  const { user } = useAuth();
  if (user === null) return <Loading />;
  if (!user) return <Navigate to="/login" replace />;
  if (role === "admin" && user.role !== "admin") return <Navigate to="/" replace />;
  if (role === "member" && user.role === "admin") return <Navigate to="/admin" replace />;
  return <AppLayout>{children}</AppLayout>;
}

function LoginRoute() {
  const { user } = useAuth();
  if (user === null) return <Loading />;
  if (user) return <Navigate to={user.role === "admin" ? "/admin" : "/"} replace />;
  return <Login />;
}

function App() {
  return (
    <div className="App">
      <BrowserRouter>
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
      </BrowserRouter>
    </div>
  );
}

export default App;
