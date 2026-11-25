import { Link } from "react-router-dom"

import { StarRating } from "@/components/reviews/StarRating"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { formatPostedDate } from "@/lib/format"
import { getInitials } from "@/lib/utils"
import type { Review } from "@/types"

export function ReviewCard({ review }: { review: Review }) {
  return (
    <article className="space-y-3 rounded-lg border border-border bg-card p-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex min-w-0 items-center gap-2.5">
          <Avatar className="size-8 shrink-0">
            {review.reviewer.avatar ? (
              <AvatarImage
                src={review.reviewer.avatar}
                alt={review.reviewer.name}
              />
            ) : null}
            <AvatarFallback className="bg-secondary text-xs font-semibold text-secondary-foreground">
              {getInitials(review.reviewer.name)}
            </AvatarFallback>
          </Avatar>
          <div className="min-w-0">
            <Link
              to={`/users/${review.reviewer.id}`}
              className="block truncate text-sm font-medium text-foreground hover:text-primary"
            >
              {review.reviewer.name}
            </Link>
            <p className="text-xs text-muted-foreground">
              {review.created_at ? formatPostedDate(review.created_at) : ""}
            </p>
          </div>
        </div>

        <StarRating value={review.rating} compact />
      </div>

      <p className="whitespace-pre-line text-sm text-foreground">
        {review.comment ?? "No comment left."}
      </p>

      <Link
        to={`/tasks/${review.task.id}`}
        className="text-xs text-muted-foreground hover:text-primary"
      >
        For project: {review.task.title}
      </Link>
    </article>
  )
}
