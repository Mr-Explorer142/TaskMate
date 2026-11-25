import { api } from "@/api/client"
import type {
  ApiEnvelope,
  Paginated,
  Task,
  TaskFilters,
  TaskPayload,
  Team,
  TeamMember,
  TeamMeta,
} from "@/types"

function toParams(filters: TaskFilters) {
  const filter: Record<string, string | number | boolean> = {}

  if (filters.search?.trim()) {
    filter.search = filters.search.trim()
  }

  if (filters.type) {
    filter.type = filters.type
  }

  if (filters.status) {
    filter.status = filters.status
  }

  if (filters.skill?.trim()) {
    filter.skill = filters.skill.trim()
  }

  if (typeof filters.min_reward === "number" && Number.isFinite(filters.min_reward)) {
    filter.min_reward = filters.min_reward
  }

  if (typeof filters.max_reward === "number" && Number.isFinite(filters.max_reward)) {
    filter.max_reward = filters.max_reward
  }

  if (filters.has_open_slots) {
    filter.has_open_slots = 1
  }

  if (typeof filters.uploader_id === "number") {
    filter.uploader_id = filters.uploader_id
  }

  return {
    filter: Object.keys(filter).length > 0 ? filter : undefined,
    sort: filters.sort,
    page: filters.page,
    per_page: filters.per_page,
  }
}

export async function listTasks(
  filters: TaskFilters = {},
  signal?: AbortSignal
): Promise<Paginated<Task>> {
  const { data } = await api.get<Paginated<Task>>("/tasks", {
    params: toParams(filters),
    signal,
  })

  return data
}

export async function myTasks(
  filters: TaskFilters = {},
  signal?: AbortSignal
): Promise<Paginated<Task>> {
  const { data } = await api.get<Paginated<Task>>("/my/tasks", {
    params: toParams(filters),
    signal,
  })

  return data
}

export async function getTask(id: number): Promise<Task> {
  const { data } = await api.get<ApiEnvelope<Task>>(`/tasks/${id}`)
  return data.data
}

export async function getTaskTeam(id: number): Promise<Team> {
  const { data } = await api.get<{ data: TeamMember[]; meta: TeamMeta }>(
    `/tasks/${id}/team`
  )

  return { members: data.data, meta: data.meta }
}

export async function startTask(id: number): Promise<Task> {
  const { data } = await api.patch<ApiEnvelope<Task>>(`/tasks/${id}/start`)
  return data.data
}

export async function createTask(payload: TaskPayload): Promise<Task> {
  const { data } = await api.post<ApiEnvelope<Task>>("/tasks", payload)
  return data.data
}

export async function updateTask(
  id: number,
  payload: Partial<TaskPayload>
): Promise<Task> {
  const { data } = await api.put<ApiEnvelope<Task>>(`/tasks/${id}`, payload)
  return data.data
}

export async function cancelTask(id: number): Promise<Task> {
  const { data } = await api.patch<ApiEnvelope<Task>>(`/tasks/${id}/cancel`)
  return data.data
}

export async function deleteTask(id: number): Promise<void> {
  await api.delete(`/tasks/${id}`)
}
