import { STATUS } from "@/lib/constants";

export function StatusBadge({ status, testid }) {
  const s = STATUS[status] || STATUS.concept;
  return (
    <span
      data-testid={testid || `status-${status}`}
      className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ${s.bg} ${s.text}`}
    >
      <span className={`h-1.5 w-1.5 rounded-full ${s.dot}`} />
      {s.label}
    </span>
  );
}
