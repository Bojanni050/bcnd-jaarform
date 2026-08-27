export const STATUS = {
  concept: { label: "Concept", bg: "bg-neutral-100", text: "text-neutral-700", dot: "bg-neutral-400" },
  ingediend: { label: "Ingediend", bg: "bg-blue-50", text: "text-blue-800", dot: "bg-blue-500" },
  in_beoordeling: { label: "In beoordeling", bg: "bg-purple-50", text: "text-purple-800", dot: "bg-purple-500" },
  aanpassing_gevraagd: { label: "Aanpassing gevraagd", bg: "bg-orange-50", text: "text-orange-800", dot: "bg-orange-500" },
  goedgekeurd: { label: "Goedgekeurd", bg: "bg-green-50", text: "text-green-800", dot: "bg-green-600" },
  afgekeurd: { label: "Afgekeurd", bg: "bg-red-50", text: "text-red-800", dot: "bg-red-600" },
};

export const ACTIVITY_TYPES = [
  { value: "externe_bijscholing", label: "Externe bijscholing" },
  { value: "bcnd_bijscholing", label: "BCND-bijscholing" },
  { value: "bcnd_ledenbijeenkomst", label: "BCND ledenbijeenkomst" },
  { value: "overige_activiteit", label: "Overige activiteit" },
];

export const ACTIVITY_LABEL = Object.fromEntries(ACTIVITY_TYPES.map((a) => [a.value, a.label]));
