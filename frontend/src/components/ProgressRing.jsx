import { motion } from "framer-motion";

const RING_TRANSITION = { duration: 1, ease: "easeOut" };

export function ProgressRing({ value, max, label, sublabel, complete, size = 140, testid }) {
  const pct = max === 0 ? 100 : Math.min(100, Math.round(((value || 0) / max) * 100));
  const radius = (size - 16) / 2;
  const circ = 2 * Math.PI * radius;
  const offset = circ - (pct / 100) * circ;
  const color = complete ? "#064413" : pct >= 60 ? "#6C8C61" : "#D97757";

  return (
    <div className="flex flex-col items-center" data-testid={testid}>
      <div className="relative" style={{ width: size, height: size }}>
        <svg width={size} height={size} className="-rotate-90">
          <circle cx={size / 2} cy={size / 2} r={radius} fill="none" stroke="#F0EEE4" strokeWidth="10" />
          <motion.circle
            cx={size / 2} cy={size / 2} r={radius} fill="none" stroke={color} strokeWidth="10"
            strokeLinecap="round" strokeDasharray={circ}
            initial={{ strokeDashoffset: circ }}
            animate={{ strokeDashoffset: offset }}
            transition={RING_TRANSITION}
          />
        </svg>
        <div className="absolute inset-0 flex flex-col items-center justify-center">
          <span className="font-heading text-2xl font-semibold text-forest">
            {value}
            <span className="text-neutral-400 text-lg">/{max}</span>
          </span>
          <span className="text-xs text-neutral-500">{sublabel}</span>
        </div>
      </div>
      <span className="mt-2 overline text-neutral-600">{label}</span>
    </div>
  );
}
