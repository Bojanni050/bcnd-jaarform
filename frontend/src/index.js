import "@/wp-public-path";
import React from "react";
import ReactDOM from "react-dom/client";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import "@/index.css";
import App from "@/App";

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 60_000,
      refetchOnWindowFocus: false,
    },
  },
});

// In WordPress, jump to the initial route for this admin page / portal.
if (typeof window !== "undefined" && window.BCND && window.BCND.initialRoute) {
  if (!window.location.hash || window.location.hash === "#/") {
    window.location.hash = window.BCND.initialRoute;
  }
}

const mountEl =
  document.getElementById("bcnd-portal-root") ||
  document.getElementById("bcnd-admin-root") ||
  document.getElementById("root");

const root = ReactDOM.createRoot(mountEl);
root.render(
  <React.StrictMode>
    <QueryClientProvider client={queryClient}>
      <App />
    </QueryClientProvider>
  </React.StrictMode>,
);
