import { createContext, useContext, useEffect, useState, useCallback, useMemo } from "react";
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
    } catch (err) {
      // Not authenticated yet: treat as anonymous, but log for diagnostics.
      if (err.response?.status && err.response.status !== 401) {
        console.error("Auth check mislukt:", err);
      }
      setUser(false);
    }
  }, []);

  useEffect(() => { if (!IS_WP) check(); }, [check]);

  const login = useCallback(async (email, password) => {
    setError("");
    if (IS_WP) {
      window.location.href = window.BCND.loginUrl;
      return;
    }
    try {
      const { data } = await api.post("/auth/login", { email, password });
      setUser(data);
      return data;
    } catch (err) {
      const msg = formatApiError(err.response?.data?.detail) || err.message;
      setError(msg);
      throw new Error(msg);
    }
  }, []);

  const logout = useCallback(async () => {
    if (IS_WP) {
      window.location.href = window.BCND.logoutUrl;
      return;
    }
    try {
      await api.post("/auth/logout");
    } catch (err) {
      console.error("Uitloggen mislukt:", err);
    }
    setUser(false);
  }, []);

  const value = useMemo(
    () => ({ user, error, setError, login, logout, refresh: check }),
    [user, error, login, logout, check],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export const useAuth = () => useContext(AuthContext);
