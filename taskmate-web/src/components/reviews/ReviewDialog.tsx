import { useState } from "react"
import { Send } from "lucide-react"

import { RatingInput } from "@/components/reviews/StarRating"
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
import { cn } from "@/lib/utils"
import type { PendingReviewee, ReviewFormPayload } from "@/types"

const MAX_COMMENT = 1000

export function ReviewDialog({
  reviewee,
  submitting,
  onClose,
  onSubmit,
}: {
  reviewee: PendingReviewee | null
  submitting: boolean
  onClose: () => void
  onSubmit: (payload: ReviewFormPayload) => void
}) {
  const [rating, setRating] = useState(5)
  const [comment, setComment] = useState("")
  const open = reviewee !== null
  const [wasOpen, setWasOpen] = useState(open)

  if (open !== wasOpen) {
    setWasOpen(open)

    if (open) {
      setRating(5)
      setComment("")
    }
  }

  function handleSubmit() {
    const trimmed = comment.trim()

    onSubmit({
      rating,
      comment: trimmed === "" ? null : trimmed,
    })
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) {
          onClose()
        }
      }}
    >
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Write a review</DialogTitle>
          <DialogDescription>
            Share how it was working with{" "}
            <span className="font-medium text-foreground">{reviewee?.name}</span>.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-2">
            <Label>Rating</Label>
            <RatingInput
              value={rating}
              onChange={setRating}
              disabled={submitting}
            />
          </div>

          <div className="space-y-2">
            <div className="flex items-center justify-between gap-2">
              <Label htmlFor="review-comment">Comment</Label>
              <span
                className={cn(
                  "text-xs text-muted-foreground",
                  comment.length === MAX_COMMENT && "text-status-danger"
                )}
              >
                {comment.length} / {MAX_COMMENT}
              </span>
            </div>
            <Textarea
              id="review-comment"
              value={comment}
              maxLength={MAX_COMMENT}
              onChange={(event) => setComment(event.target.value)}
              placeholder="What stood out about working together?"
              disabled={submitting}
            />
          </div>
        </div>

        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            onClick={onClose}
            disabled={submitting}
          >
            Cancel
          </Button>
          <Button type="button" onClick={handleSubmit} disabled={submitting}>
            {submitting ? "Submitting..." : "Submit review"}
            {!submitting ? <Send className="size-4" /> : null}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
