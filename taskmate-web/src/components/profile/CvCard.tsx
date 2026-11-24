import { useRef, useState } from "react"
import { format } from "date-fns"
import { Download, FileText, Loader2, Trash2, Upload } from "lucide-react"
import { toast } from "sonner"

import { getApiErrorMessage } from "@/api/client"
import { deleteCv, downloadCv, uploadCv } from "@/api/profile"
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { useAuthStore } from "@/stores/useAuthStore"
import type { User } from "@/types"

const MAX_BYTES = 5 * 1024 * 1024
const ALLOWED_EXTENSIONS = ["pdf", "doc", "docx"]

function saveBlob(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement("a")
  anchor.href = url
  anchor.download = filename
  document.body.appendChild(anchor)
  anchor.click()
  anchor.remove()
  URL.revokeObjectURL(url)
}

export function CvCard({ user }: { user: User }) {
  const setUser = useAuthStore((state) => state.setUser)
  const inputRef = useRef<HTMLInputElement>(null)
  const [progress, setProgress] = useState<number | null>(null)
  const [downloading, setDownloading] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const [confirmOpen, setConfirmOpen] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const uploading = progress !== null

  function openFilePicker() {
    setError(null)
    inputRef.current?.click()
  }

  async function handleFile(file: File) {
    const extension = file.name.split(".").pop()?.toLowerCase() ?? ""

    if (!ALLOWED_EXTENSIONS.includes(extension)) {
      setError("Upload a PDF, DOC, or DOCX file.")
      return
    }

    if (file.size > MAX_BYTES) {
      setError("Your CV must be 5 MB or smaller.")
      return
    }

    setError(null)
    setProgress(0)

    try {
      const updated = await uploadCv(file, (percent) => setProgress(percent))
      setUser(updated)
      toast.success(user.has_cv ? "CV replaced." : "CV uploaded.")
    } catch (uploadError) {
      toast.error(getApiErrorMessage(uploadError))
    } finally {
      setProgress(null)
      if (inputRef.current) {
        inputRef.current.value = ""
      }
    }
  }

  async function handleDownload() {
    setDownloading(true)

    try {
      const { blob, filename } = await downloadCv(user.id)
      saveBlob(blob, filename)
    } catch (downloadError) {
      toast.error(getApiErrorMessage(downloadError))
    } finally {
      setDownloading(false)
    }
  }

  async function handleDelete() {
    setDeleting(true)

    try {
      const updated = await deleteCv()
      setUser(updated)
      toast.success("CV deleted.")
      setConfirmOpen(false)
    } catch (deleteError) {
      toast.error(getApiErrorMessage(deleteError))
    } finally {
      setDeleting(false)
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>CV</CardTitle>
        <CardDescription>
          PDF, DOC, or DOCX up to 5 MB. Only logged-in users can download it.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <input
          ref={inputRef}
          type="file"
          accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
          className="hidden"
          onChange={(event) => {
            const file = event.target.files?.[0]
            if (file) {
              void handleFile(file)
            }
          }}
        />

        {user.has_cv ? (
          <div className="flex flex-col gap-3 rounded-lg border border-border bg-background p-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex min-w-0 items-start gap-3">
              <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-secondary text-secondary-foreground">
                <FileText className="size-5" />
              </span>
              <div className="min-w-0">
                <p className="truncate font-medium text-foreground">
                  {user.cv_original_name ?? "CV"}
                </p>
                <p className="text-sm text-muted-foreground">
                  {user.cv_uploaded_at
                    ? `Uploaded ${format(new Date(user.cv_uploaded_at), "MMM d, yyyy")}`
                    : "Uploaded"}
                </p>
              </div>
            </div>

            <div className="flex flex-wrap gap-2">
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => void handleDownload()}
                disabled={downloading}
              >
                {downloading ? (
                  <Loader2 className="size-4 animate-spin" />
                ) : (
                  <Download className="size-4" />
                )}
                {downloading ? "Downloading..." : "Download"}
              </Button>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={openFilePicker}
                disabled={uploading}
              >
                <Upload className="size-4" />
                Replace
              </Button>
              <Button
                type="button"
                variant="destructive"
                size="sm"
                onClick={() => setConfirmOpen(true)}
                disabled={deleting}
              >
                <Trash2 className="size-4" />
                Delete
              </Button>
            </div>
          </div>
        ) : (
          <button
            type="button"
            onClick={openFilePicker}
            disabled={uploading}
            className="flex w-full flex-col items-center gap-2 rounded-lg border border-dashed border-border bg-background px-6 py-10 text-center transition-colors hover:border-primary/50 hover:bg-secondary/40 disabled:cursor-not-allowed disabled:opacity-60"
          >
            <span className="flex size-10 items-center justify-center rounded-lg bg-secondary text-secondary-foreground">
              <Upload className="size-5" />
            </span>
            <span className="font-medium text-foreground">Upload your CV</span>
            <span className="text-sm text-muted-foreground">
              Click to choose a file — PDF, DOC, or DOCX up to 5 MB
            </span>
          </button>
        )}

        {uploading ? (
          <div className="space-y-1.5">
            <div className="flex items-center justify-between text-sm">
              <span className="text-muted-foreground">Uploading...</span>
              <span className="font-medium text-foreground">{progress}%</span>
            </div>
            <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
              <div
                className="h-full rounded-full bg-primary transition-all"
                style={{ width: `${progress ?? 0}%` }}
              />
            </div>
          </div>
        ) : null}

        {error ? <p className="text-sm text-destructive">{error}</p> : null}
      </CardContent>

      <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete your CV?</AlertDialogTitle>
            <AlertDialogDescription>
              This removes the file from your profile. You can upload a new CV at
              any time.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleting}>Cancel</AlertDialogCancel>
            <AlertDialogAction
              variant="destructive"
              disabled={deleting}
              onClick={(event) => {
                event.preventDefault()
                void handleDelete()
              }}
            >
              {deleting ? "Deleting..." : "Delete CV"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </Card>
  )
}
