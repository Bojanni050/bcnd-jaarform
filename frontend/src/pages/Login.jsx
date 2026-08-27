import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "@/context/AuthContext";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Leaf, Loader2 } from "lucide-react";

export default function Login() {
  const { login, error } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);

  const submit = async (e) => {
    e.preventDefault();
    setLoading(true);
    try {
      const u = await login(email, password);
      navigate(u.role === "admin" ? "/admin" : "/");
    } catch (_) {} finally { setLoading(false); }
  };

  return (
    <div className="min-h-screen grid md:grid-cols-2">
      <div className="hidden md:block relative">
        <img
          src="https://images.unsplash.com/photo-1770836037816-4445dbd449fd?crop=entropy&cs=srgb&fm=jpg&q=85&w=1200"
          alt="Dierenarts" className="absolute inset-0 h-full w-full object-cover"
        />
        <div className="absolute inset-0 bg-[#1E3F33]/70" />
        <div className="relative z-10 h-full flex flex-col justify-between p-12 text-white">
          <div className="flex items-center gap-2.5">
            <div className="h-10 w-10 rounded-lg bg-terracotta flex items-center justify-center">
              <Leaf className="h-6 w-6" />
            </div>
            <span className="font-heading text-lg font-semibold">BCND</span>
          </div>
          <div>
            <h1 className="font-heading text-4xl font-semibold leading-tight tracking-tight">
              Bij- en nascholings-<br />administratie
            </h1>
            <p className="mt-4 text-white/70 max-w-md">
              Beroepsvereniging van Complementaire en Natuurlijke geneeswijzen voor Dieren.
              Registreer bijscholingen, consulten en jaarformulieren op één plek.
            </p>
          </div>
          <div className="text-xs text-white/40">© {new Date().getFullYear()} BCND</div>
        </div>
      </div>

      <div className="flex items-center justify-center p-6 md:p-12 bg-bone">
        <div className="w-full max-w-sm">
          <div className="md:hidden flex items-center gap-2.5 mb-8">
            <div className="h-10 w-10 rounded-lg bg-forest flex items-center justify-center">
              <Leaf className="h-6 w-6 text-white" />
            </div>
            <span className="font-heading text-lg font-semibold text-forest">BCND</span>
          </div>
          <p className="overline text-terracotta mb-2">Inloggen</p>
          <h2 className="font-heading text-3xl font-semibold tracking-tight text-forest mb-1">Welkom terug</h2>
          <p className="text-sm text-neutral-500 mb-8">Log in op uw leden- of beheeromgeving.</p>

          <form onSubmit={submit} className="space-y-4">
            <div>
              <Label htmlFor="email" className="overline text-neutral-600">E-mailadres</Label>
              <Input
                id="email" data-testid="login-email" type="email" required value={email}
                onChange={(e) => setEmail(e.target.value)} placeholder="naam@voorbeeld.nl" className="mt-1.5"
              />
            </div>
            <div>
              <Label htmlFor="password" className="overline text-neutral-600">Wachtwoord</Label>
              <Input
                id="password" data-testid="login-password" type="password" required value={password}
                onChange={(e) => setPassword(e.target.value)} placeholder="••••••••" className="mt-1.5"
              />
            </div>
            {error && <p className="text-sm text-red-600" data-testid="login-error">{error}</p>}
            <Button data-testid="login-submit" type="submit" disabled={loading}
              className="w-full bg-forest hover:bg-forest-hover text-white">
              {loading && <Loader2 className="h-4 w-4 mr-2 animate-spin" />} Inloggen
            </Button>
          </form>
        </div>
      </div>
    </div>
  );
}
