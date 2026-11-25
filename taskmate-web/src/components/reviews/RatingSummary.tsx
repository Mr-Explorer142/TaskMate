import { StarRating } from "@/components/reviews/StarRating"
import type { ReviewSummary } from "@/types"

export function RatingSummary({ summary }: { summary: ReviewSummary }) {
  return (
    <div className="space-y-4 rounded-lg border border-border bg-muted/30 p-4">
      <StarRating
        value={summary.average_rating}
        count={summary.reviews_count}
      />

      <div className="space-y-1.5">
        {([5, 4, 3, 2, 1] as const).map((stars) => {
          const count = summary.distribution[stars] ?? 0
          const percent = summary.reviews_count > 0
            ? Math.round((count / summary.reviews_count) * 100)
            : 0

          return (
            <div key={stars} className="flex items-center gap-2 text-xs">
              <span className="w-8 text-muted-foreground">{stars} star</span>
              <div className="h-1.5 flex-1 overflow-hidden rounded-lg bg-border">
                <div
                  className="h-full rounded-lg bg-primary"
                  style={{ width: `${percent}%` }}
                />
              </div>
              <span className="w-6 text-right text-muted-foreground">
                {count}
              </span>
            </div>
          )
        })}
      </div>
    </div>
  )
}
