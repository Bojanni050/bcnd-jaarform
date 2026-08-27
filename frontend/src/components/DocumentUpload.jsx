import { useState } from "react";
import { UploadCloud, FileText, Loader2, X } from "lucide-react";
import api from "@/lib/api";
import { toast } from "sonner";

export function DocumentUpload({ trainingId, onUploaded, compact }) {
  const [uploading, setUploading] = useState(false);
  const [drag, setDrag] = useState(false);

  const upload = async (file) => {
    if (!file) return;
    setUploading(true);
    const fd = new FormData();
    fd.append("file", file);
    if (trainingId) fd.append("training_id", trainingId);
    try {
      const { data } = await api.post("/documents/upload", fd, { headers: { "Content-Type": "multipart/form-data" } });
      toast.success("Deelnamebewijs geüpload");
      onUploaded && onUploaded(data);
    } catch (e) {
      toast.error(e.response?.data?.detail || "Upload mislukt");
    } finally { setUploading(false); }
  };

  return (
    <label
      data-testid="document-upload-zone"
      onDragOver={(e) => { e.preventDefault(); setDrag(true); }}
      onDragLeave={() => setDrag(false)}
      onDrop={(e) => { e.preventDefault(); setDrag(false); upload(e.dataTransfer.files[0]); }}
      className={`flex ${compact ? "flex-row items-center gap-3 py-2 px-3" : "flex-col items-center py-6 px-4"} justify-center rounded-lg border-2 border-dashed cursor-pointer transition-colors ${
        drag ? "border-forest bg-neutral-50" : "border-neutral-300 hover:border-sage"
      }`}
    >
      <input type="file" className="hidden" accept=".pdf,.png,.jpg,.jpeg,.gif,.webp"
        onChange={(e) => upload(e.target.files[0])} data-testid="document-file-input" />
      {uploading ? <Loader2 className="h-5 w-5 animate-spin text-forest" /> : <UploadCloud className="h-5 w-5 text-neutral-400" />}
      <div className={compact ? "" : "mt-2 text-center"}>
        <div className="text-sm text-neutral-600">{uploading ? "Bezig met uploaden…" : "Sleep bestand of klik om te uploaden"}</div>
        {!compact && <div className="text-xs text-neutral-400 mt-0.5">PDF of afbeelding, max 15MB</div>}
      </div>
    </label>
  );
}

export function DocumentChip({ doc, onDelete }) {
  const view = async () => {
    try {
      const res = await api.get(`/documents/${doc.id}/download`, { responseType: "blob" });
      const url = URL.createObjectURL(res.data);
      window.open(url, "_blank");
    } catch { toast.error("Kan document niet openen"); }
  };
  return (
    <span className="inline-flex items-center gap-2 rounded-md bg-neutral-100 px-2.5 py-1 text-xs" data-testid={`doc-chip-${doc.id}`}>
      <FileText className="h-3.5 w-3.5 text-forest" />
      <button onClick={view} className="hover:underline max-w-[160px] truncate" data-testid={`doc-open-${doc.id}`}>
        {doc.original_filename}
      </button>
      {onDelete && (
        <button onClick={() => onDelete(doc)} data-testid={`doc-delete-${doc.id}`}>
          <X className="h-3.5 w-3.5 text-neutral-400 hover:text-red-600" />
        </button>
      )}
    </span>
  );
}
