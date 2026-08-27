export function Timeline({ items }) {
  if (!items || items.length === 0)
    return <p className="text-sm text-neutral-400">Nog geen historie.</p>;
  return (
    <div className="relative pl-5" data-testid="audit-timeline">
      <div className="absolute left-[7px] top-1 bottom-1 w-px bg-neutral-200" />
      {items.map((h, i) => (
        <div key={h.id || i} className="relative pb-4 last:pb-0">
          <div className="absolute -left-[13px] top-1 h-3 w-3 rounded-full bg-forest ring-4 ring-white" />
          <div className="text-sm text-neutral-800">
            <span className="font-medium">{h.action?.replace(/_/g, " ")}</span>
            {h.remark ? <span className="text-neutral-500"> — {h.remark}</span> : null}
          </div>
          <div className="text-xs text-neutral-400 mt-0.5">
            {formatDT(h.created_at)} · {h.actor_name} ({h.actor_role === "admin" ? "Admin" : "Lid"})
          </div>
        </div>
      ))}
    </div>
  );
}

export function formatDT(iso) {
  if (!iso) return "";
  try {
    const d = new Date(iso);
    return d.toLocaleString("nl-NL", { day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" });
  } catch { return iso; }
}

export function formatDate(iso) {
  if (!iso) return "";
  try {
    return new Date(iso).toLocaleDateString("nl-NL", { day: "2-digit", month: "2-digit", year: "numeric" });
  } catch { return iso; }
}
