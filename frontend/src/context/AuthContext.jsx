import { createContext, useContext, useEffect, useState, useCallback } from "react";
import api, { formatApiError, IS_WP } from "@/lib/api";

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(IS_WP ? (window.BCND.currentUser || false) : null);
  const [error, setError] = useState("");

  const check = useCallback(async () => {
    if (IS_WP) {
      setUser(window.BCND.currentUser || false);
      return;
    }
    try {
      const { data } = await api.get("/auth/me");
      setUser(data);
    } catch (e) {
      setUser(false);
    }
  }, []);

  useEffect(() => { if (!IS_WP) check(); }, [check]);

  const login = async (email, password) => {
    setError("");
    if (IS_WP) {
      window.location.href = window.BCND.loginUrl;
      return;
    }
    try {
      const { data } = await api.post("/auth/login", { email, password });
      setUser(data);
      return data;
    } catch (e) {
      const msg = formatApiError(e.response?.data?.detail) || e.message;
      setError(msg);
      throw new Error(msg);
    }
  };

  const logout = async () => {
    if (IS_WP) {
      window.location.href = window.BCND.logoutUrl;
      return;
    }
    try { await api.post("/auth/logout"); } catch (e) {}
    setUser(false);
  };

  return (
    <AuthContext.Provider value={{ user, error, setError, login, logout, refresh: check }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => useContext(AuthContext);
