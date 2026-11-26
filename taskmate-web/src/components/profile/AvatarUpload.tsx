import { useEffect, useRef, useState } from "react"
import { ImagePlus, Loader2, Trash2 } from "lucide-react"
import { toast } from "sonner"

import { deleteAvatar, uploadAvatar } from "@/api/profile"
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
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { getInitials } from "@/lib/utils"
import { useAuthStore } from "@/stores/useAuthStore"
import type { User } from "@/types"

const MAX_BYTES = 2 * 1024 * 1024
const ALLOWED_TYPES = ["image/jpeg", "image/png", "image/webp"]

export function AvatarUpload({ user }: { user: User }) {
  const setUser = useAuthStore((state) => state.setUser)
  const inputRef = useRef<HTMLInputElement>(null)
  const [preview, setPreview] = useState<string | null>(null)
  const [progress, setProgress] = useState<number | null>(null)
  const [dragging, setDragging] = useState(false)
  const [confirmOpen, setConfirmOpen] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const uploading = progress !== null
  const imageSrc = preview ?? user.avatar_url

  useEffect(() => {
    return () => {
      if (preview?.startsWith("blob:")) {
        URL.revokeObjectURL(preview)
      }
    }
  }, [preview])

  function openFilePicker() {
    setError(null)
    inputRef.current?.click()
  }

  async function handleFile(file: File) {
    if (!ALLOWED_TYPES.includes(file.type)) {
      setError("Choose a JPG, PNG, or WebP image.")
      return
    }

    if (file.size > MAX_BYTES) {
      setError("Your avatar must be 2 MB or smaller.")
      return
    }

    const objectUrl = URL.createObjectURL(file)
    setPreview(objectUrl)
    setError(null)
    setProgress(0)

    try {
      const updated = await uploadAvatar(file, setProgress)
      setUser(updated)
      toast.success(user.avatar_url ? "Avatar updated." : "Avatar uploaded.")
    } catch (uploadError) {
      setPreview(null)
      toast.error(getApiErrorMessage(uploadError))
    } finally {
      setProgress(null)
      if (inputRef.current) {
        inputRef.current.value = ""
      }
    }
  }

  async function handleRemove() {
    setProgress(0)

    try {
      const updated = await deleteAvatar()
      setUser(updated)
      setPreview(null)
      setConfirmOpen(false)
      toast.success("Avatar removed.")
    } catch (deleteError) {
      toast.error(getApiErrorMessage(deleteError))
    } finally {
      setProgress(null)
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Profile picture</CardTitle>
        <CardDescription>
          JPG, PNG, or WebP up to 2 MB. Your initials are shown until you add a
          picture.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <input
          ref={inputRef}
          type="file"
          accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
          className="hidden"
          onChange={(event) => {
            const file = event.target.files?.[0]
            if (file) {
              void handleFile(file)
            }
          }}
        />

        <div
          onDragOver={(event) => {
            event.preventDefault()
            setDragging(true)
          }}
          onDragLeave={() => setDragging(false)}
          onDrop={(event) => {
            event.preventDefault()
            setDragging(false)
            const file = event.dataTransfer.files[0]
            if (file && !uploading) {
              void handleFile(file)
            }
          }}
          className="flex flex-col items-center gap-4 rounded-lg border border-dashed border-border bg-background p-6 sm:flex-row sm:justify-between"
        >
          <div className="flex min-w-0 items-center gap-4">
            <Avatar className="size-16 shrink-0">
              {imageSrc ? <AvatarImage src={imageSrc} alt={user.name} /> : null}
              <AvatarFallback className="bg-secondary text-lg font-semibold text-secondary-foreground">
                {getInitials(user.name)}
              </AvatarFallback>
            </Avatar>

            <div className="min-w-0">
              <p className="font-medium text-foreground">
                {user.avatar_url ? "Current picture" : "No picture yet"}
              </p>
              <p className="text-sm text-muted-foreground">
                Click to choose a file or drop one here.
              </p>
            </div>
          </div>

          <div className={`flex shrink-0 flex-wrap gap-2 ${dragging ? "opacity-70" : ""}`}>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={openFilePicker}
              disabled={uploading}
            >
              {uploading ? (
                <Loader2 className="size-4 animate-spin" />
              ) : (
                <ImagePlus className="size-4" />
              )}
              {uploading ? "Uploading..." : user.avatar_url ? "Change" : "Upload"}
            </Button>
            <Button
              type="button"
              variant="destructive"
              size="sm"
              onClick={() => setConfirmOpen(true)}
              disabled={uploading || !user.avatar_url}
            >
              <Trash2 className="size-4" />
              Remove
            </Button>
          </div>
        </div>

        {uploading ? (
          <div className="mt-3 space-y-1.5">
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

        {error ? <p className="mt-3 text-sm text-destructive">{error}</p> : null}

        <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>Remove your profile picture?</AlertDialogTitle>
              <AlertDialogDescription>
                Your initials will be shown until you upload a new picture.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel disabled={uploading}>Cancel</AlertDialogCancel>
              <AlertDialogAction
                variant="destructive"
                disabled={uploading}
                onClick={(event) => {
                  event.preventDefault()
                  void handleRemove()
                }}
              >
                {uploading ? "Removing..." : "Remove picture"}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </CardContent>
    </Card>
  )
}
