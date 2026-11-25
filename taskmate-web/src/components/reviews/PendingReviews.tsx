import { useCallback, useEffect, useState } from "react"
import { Star } from "lucide-react"
import { toast } from "sonner"

import { getApiErrorMessage } from "@/api/client"
import { pending, submit } from "@/api/reviews"
import { ErrorState } from "@/components/PageStates"
import { ReviewDialog } from "@/components/reviews/ReviewDialog"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Skeleton } from "@/components/ui/skeleton"
import { getInitials } from "@/lib/utils"
import type { PendingReviewee, ReviewFormPayload } from "@/types"

export function PendingReviews({
  taskId,
  onReviewed,
}: {
  taskId: number
  onReviewed: () => void
}) {
  const [members, setMembers] = useState<PendingReviewee[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [selected, setSelected] = useState<PendingReviewee | null>(null)
  const [submitting, setSubmitting] = useState(false)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)

    try {
      setMembers(await pending(taskId))
    } catch (caught) {
      setError(getApiErrorMessage(caught))
    } finally {
      setLoading(false)
    }
  }, [taskId])

  useEffect(() => {
    void load()
  }, [load])

  async function handleSubmit(payload: ReviewFormPayload) {
    if (!selected) {
      return
    }

    setSubmitting(true)

    try {
      await submit(taskId, {
        reviewee_id: selected.id,
        ...payload,
      })

      toast.success("Review submitted.")
      setSelected(null)
      await load()
      onReviewed()
    } catch (caught) {
      toast.error(getApiErrorMessage(caught))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Star className="size-4 text-primary" />
          Review your team
        </CardTitle>
      </CardHeader>
      <CardContent>
        {loading ? (
          <div className="space-y-2">
            <Skeleton className="h-12 w-full rounded-lg" />
            <Skeleton className="h-12 w-full rounded-lg" />
          </div>
        ) : error ? (
          <ErrorState
            title="Could not load pending reviews"
            message={error}
            onRetry={() => void load()}
          />
        ) : members.length === 0 ? (
          <p className="text-sm text-muted-foreground">
            You have reviewed everyone.
          </p>
        ) : (
          <ul className="space-y-2">
            {members.map((member) => (
              <li
                key={member.id}
                className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-muted/30 px-3 py-2"
              >
                <div className="flex min-w-0 items-center gap-2.5">
                  <Avatar className="size-8">
                    {member.avatar ? (
                      <AvatarImage src={member.avatar} alt={member.name} />
                    ) : null}
                    <AvatarFallback className="bg-secondary text-xs font-semibold text-secondary-foreground">
                      {getInitials(member.name)}
                    </AvatarFallback>
                  </Avatar>
                  <span className="truncate text-sm font-medium text-foreground">
                    {member.name}
                  </span>
                </div>
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => setSelected(member)}
                  disabled={submitting}
                >
                  Write review
                </Button>
              </li>
            ))}
          </ul>
        )}
      </CardContent>

      <ReviewDialog
        reviewee={selected}
        submitting={submitting}
        onClose={() => setSelected(null)}
        onSubmit={(payload) => void handleSubmit(payload)}
      />
    </Card>
  )
}
