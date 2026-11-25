import { api } from "@/api/client"
import type {
  ApiEnvelope,
  Paginated,
  PointTransaction,
  PointsSummary,
} from "@/types"

export async function summary(): Promise<PointsSummary> {
  const { data } = await api.get<ApiEnvelope<PointsSummary>>("/points")
  return data.data
}

export async function transactions(
  page = 1
): Promise<Paginated<PointTransaction>> {
  const { data } = await api.get<Paginated<PointTransaction>>(
    "/points/transactions",
    { params: { page } }
  )

  return data
}
