import { useCallback, useEffect, useState } from "react"
import { Star } from "lucide-react"

import { getApiErrorMessage } from "@/api/client"
import { userReviews } from "@/api/reviews"
import { EmptyState, ErrorState } from "@/components/PageStates"
import { RatingSummary } from "@/components/reviews/RatingSummary"
import { ReviewCard } from "@/components/reviews/ReviewCard"
import { Pagination } from "@/components/tasks/Pagination"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Skeleton } from "@/components/ui/skeleton"
import type { Review, ReviewSummary } from "@/types"

export function ReviewsReceived({ userId }: { userId: number }) {
  const [reviews, setReviews] = useState<Review[]>([])
  const [summary, setSummary] = useState<ReviewSummary | null>(null)
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(
    async (nextPage: number) => {
      setLoading(true)
      setError(null)

      try {
        const result = await userReviews(userId, nextPage)
        setReviews(result.data)
        setSummary({
          average_rating: result.meta.average_rating,
          reviews_count: result.meta.reviews_count,
          distribution: result.meta.distribution,
        })
        setPage(result.meta.current_page)
        setLastPage(result.meta.last_page)
      } catch (caught) {
        setError(getApiErrorMessage(caught))
      } finally {
        setLoading(false)
      }
    },
    [userId]
  )

  useEffect(() => {
    void load(1)
  }, [load])

  return (
    <Card id="reviews" className="scroll-mt-24">
      <CardHeader>
        <CardTitle>Reviews</CardTitle>
      </CardHeader>
      <CardContent className="space-y-5">
        {loading && reviews.length === 0 ? (
          <div className="space-y-3">
            <Skeleton className="h-28 w-full rounded-lg" />
            <Skeleton className="h-24 w-full rounded-lg" />
            <Skeleton className="h-24 w-full rounded-lg" />
          </div>
        ) : error ? (
          <ErrorState
            title="Could not load reviews"
            message={error}
            onRetry={() => void load(page)}
          />
        ) : (
          <>
            {summary ? <RatingSummary summary={summary} /> : null}

            {reviews.length === 0 ? (
              <EmptyState
                icon={Star}
                title="No reviews yet"
                description="Reviews will appear here after a completed project."
              />
            ) : (
              <div className="space-y-3">
                {reviews.map((review) => (
                  <ReviewCard key={review.id} review={review} />
                ))}
              </div>
            )}

            {lastPage > 1 ? (
              <Pagination
                page={page}
                lastPage={lastPage}
                onPageChange={(nextPage) => void load(nextPage)}
                disabled={loading}
              />
            ) : null}
          </>
        )}
      </CardContent>
    </Card>
  )
}
