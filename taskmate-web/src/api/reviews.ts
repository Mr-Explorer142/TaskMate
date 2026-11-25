import { api } from "@/api/client"
import type {
  ApiEnvelope,
  PendingReviewee,
  Review,
  ReviewList,
  ReviewPayload,
} from "@/types"

export async function pending(taskId: number): Promise<PendingReviewee[]> {
  const { data } = await api.get<ApiEnvelope<PendingReviewee[]>>(
    `/tasks/${taskId}/reviews/pending`
  )

  return data.data
}

export async function submit(
  taskId: number,
  payload: ReviewPayload
): Promise<Review> {
  const { data } = await api.post<ApiEnvelope<Review>>(
    `/tasks/${taskId}/reviews`,
    payload
  )

  return data.data
}

export async function userReviews(
  userId: number,
  page = 1
): Promise<ReviewList> {
  const { data } = await api.get<ReviewList>(`/users/${userId}/reviews`, {
    params: { page },
  })

  return data
}
