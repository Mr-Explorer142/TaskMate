import { api } from "@/api/client"
import type {
  ApiEnvelope,
  Application,
  ApplicationStatus,
  Paginated,
} from "@/types"

export interface ApplicationListFilters {
  status?: ApplicationStatus
  page?: number
}

function toParams(filters: ApplicationListFilters) {
  return {
    filter: filters.status ? { status: filters.status } : undefined,
    page: filters.page,
  }
}

export async function applyToTask(
  taskId: number,
  message?: string | null
): Promise<Application> {
  const { data } = await api.post<ApiEnvelope<Application>>(
    `/tasks/${taskId}/apply`,
    message ? { message } : {}
  )

  return data.data
}

export async function withdrawApplication(id: number): Promise<void> {
  await api.delete(`/applications/${id}`)
}

export async function listMyApplications(
  filters: ApplicationListFilters = {},
  signal?: AbortSignal
): Promise<Paginated<Application>> {
  const { data } = await api.get<Paginated<Application>>("/my/applications", {
    params: toParams(filters),
    signal,
  })

  return data
}

export async function listTaskApplications(
  taskId: number,
  filters: ApplicationListFilters = {},
  signal?: AbortSignal
): Promise<Paginated<Application>> {
  const { data } = await api.get<Paginated<Application>>(
    `/tasks/${taskId}/applications`,
    {
      params: toParams(filters),
      signal,
    }
  )

  return data
}

export async function acceptApplication(id: number): Promise<Application> {
  const { data } = await api.patch<ApiEnvelope<Application>>(
    `/applications/${id}/accept`
  )

  return data.data
}

export async function rejectApplication(id: number): Promise<Application> {
  const { data } = await api.patch<ApiEnvelope<Application>>(
    `/applications/${id}/reject`
  )

  return data.data
}
