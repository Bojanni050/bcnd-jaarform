import axios from "axios";

const WP = typeof window !== "undefined" && !!window.BCND;

// In WordPress the backend is the WP REST API (bcnd/v1). In dev it's the FastAPI server.
export const API_BASE = WP
  ? `${window.BCND.restUrl}bcnd/v1`
  : `${process.env.REACT_APP_BACKEND_URL}/api`;

const api = axios.create({
  baseURL: API_BASE,
  withCredentials: true,
});

if (WP) {
  api.interceptors.request.use((config) => {
    config.headers["X-WP-Nonce"] = window.BCND.nonce;
    return config;
  });
}

export const IS_WP = WP;

export function formatApiError(detail) {
  if (detail == null) return "Er ging iets mis. Probeer het opnieuw.";
  if (typeof detail === "string") return detail;
  if (detail && typeof detail.message === "string") return detail.message;
  if (Array.isArray(detail))
    return detail.map((e) => (e && typeof e.msg === "string" ? e.msg : JSON.stringify(e))).filter(Boolean).join(" ");
  if (detail && typeof detail.msg === "string") return detail.msg;
  return String(detail);
}

export default api;
