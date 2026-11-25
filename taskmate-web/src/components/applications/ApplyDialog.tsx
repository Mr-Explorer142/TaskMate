import { useState } from "react"
import { Loader2, Send } from "lucide-react"

import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"

const MAX_MESSAGE = 1000

export function ApplyDialog({
  open,
  onOpenChange,
  taskTitle,
  submitting,
  onSubmit,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  taskTitle: string
  submitting: boolean
  onSubmit: (message: string | null) => void
}) {
  const [message, setMessage] = useState("")
  const [wasOpen, setWasOpen] = useState(open)

  if (open !== wasOpen) {
    setWasOpen(open)

    if (open) {
      setMessage("")
    }
  }

  function handleSubmit() {
    const trimmed = message.trim()
    onSubmit(trimmed === "" ? null : trimmed)
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Apply to this task</DialogTitle>
          <DialogDescription>
            Tell the uploader why you are a good fit for{" "}
            <span className="font-medium text-foreground">{taskTitle}</span>.
            A message is optional.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-2">
          <div className="flex items-center justify-between gap-2">
            <Label htmlFor="application-message">Message</Label>
            <span className="text-xs text-muted-foreground">
              {message.length} / {MAX_MESSAGE}
            </span>
          </div>
          <Textarea
            id="application-message"
            value={message}
            maxLength={MAX_MESSAGE}
            onChange={(event) => setMessage(event.target.value)}
            placeholder="Share your relevant skills, availability, or a short introduction."
            disabled={submitting}
          />
        </div>

        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            onClick={() => onOpenChange(false)}
            disabled={submitting}
          >
            Cancel
          </Button>
          <Button type="button" onClick={handleSubmit} disabled={submitting}>
            {submitting ? (
              <Loader2 className="size-4 animate-spin" />
            ) : (
              <Send className="size-4" />
            )}
            {submitting ? "Submitting..." : "Send application"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
