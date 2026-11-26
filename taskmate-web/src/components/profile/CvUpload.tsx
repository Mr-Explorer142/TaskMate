import { useRef, useState } from "react"
import { ExternalLink, FileText, Loader2, Trash2, Upload } from "lucide-react"
import { toast } from "sonner"

import { deleteCv, getCvLink, uploadCv } from "@/api/profile"
import { getApiErrorMessage } from "@/api/client"
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
import { openInNewTab } from "@/lib/download"
import { formatDate } from "@/lib/format"
import { useAuthStore } from "@/stores/useAuthStore"
import type { User } from "@/types"

const MAX_BYTES = 5 * 1024 * 1024

export function CvUpload({ user }: { user: User }) {
  const setUser = useAuthStore((state) => state.setUser)
  const inputRef = useRef<HTMLInputElement>(null)
  const [progress, setProgress] = useState<number | null>(null)
  const [viewing, setViewing] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const [confirmOpen, setConfirmOpen] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const uploading = progress !== null

  function openFilePicker() {
    setError(null)
    inputRef.current?.click()
  }

  async function handleFile(file: File) {
    if (file.type !== "application/pdf" && !file.name.toLowerCase().endsWith(".pdf")) {
      setError("Choose a PDF file.")
      return
    }

    if (file.size > MAX_BYTES) {
      setError("Your CV must be 5 MB or smaller.")
      return
    }

    setError(null)
    setProgress(0)

    try {
      const updated = await uploadCv(file, setProgress)
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

  async function handleView() {
    setViewing(true)

    try {
      const link = await getCvLink(user.id)
      openInNewTab(link.url)
    } catch (viewError) {
      toast.error(getApiErrorMessage(viewError))
    } finally {
      setViewing(false)
    }
  }

  async function handleDelete() {
    setDeleting(true)

    try {
      const updated = await deleteCv()
      setUser(updated)
      setConfirmOpen(false)
      toast.success("CV deleted.")
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
          PDF only, up to 5 MB. Signed links last 10 minutes and are private to
          you and task owners you applied to.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <input
          ref={inputRef}
          type="file"
          accept=".pdf,application/pdf"
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
                    ? `Uploaded ${formatDate(user.cv_uploaded_at)}`
                    : "Uploaded"}
                </p>
              </div>
            </div>

            <div className="flex flex-wrap gap-2">
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => void handleView()}
                disabled={viewing || uploading || deleting}
              >
                {viewing ? (
                  <Loader2 className="size-4 animate-spin" />
                ) : (
                  <ExternalLink className="size-4" />
                )}
                {viewing ? "Opening..." : "View CV"}
              </Button>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={openFilePicker}
                disabled={uploading || deleting}
              >
                <Upload className="size-4" />
                Replace
              </Button>
              <Button
                type="button"
                variant="destructive"
                size="sm"
                onClick={() => setConfirmOpen(true)}
                disabled={uploading || deleting}
              >
                <Trash2 className="size-4" />
                Remove
              </Button>
            </div>
          </div>
        ) : (
          <button
            type="button"
            onClick={openFilePicker}
            disabled={uploading || deleting}
            className="flex w-full flex-col items-center gap-2 rounded-lg border border-dashed border-border bg-background px-6 py-10 text-center transition-colors hover:border-primary/50 hover:bg-secondary/40 disabled:cursor-not-allowed disabled:opacity-60"
          >
            <span className="flex size-10 items-center justify-center rounded-lg bg-secondary text-secondary-foreground">
              <Upload className="size-5" />
            </span>
            <span className="font-medium text-foreground">Upload your CV</span>
            <span className="text-sm text-muted-foreground">
              Click to choose a file — PDF up to 5 MB
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
            <AlertDialogTitle>Remove your CV?</AlertDialogTitle>
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
              {deleting ? "Removing..." : "Remove CV"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </Card>
  )
}
