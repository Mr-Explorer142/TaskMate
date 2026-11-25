import { api } from "@/api/client"
import type {
  ApiEnvelope,
  AppNotification,
  MarkAllReadResult,
  NotificationListFilters,
  NotificationUnreadCount,
  Paginated,
} from "@/types"

export async function list(
  filters: NotificationListFilters = {}
): Promise<Paginated<AppNotification>> {
  const { data } = await api.get<Paginated<AppNotification>>("/notifications", {
    params: {
      page: filters.page,
      filter: filters.unread ? { unread: 1 } : undefined,
    },
  })

  return data
}

export async function unreadCount(): Promise<number> {
  const { data } = await api.get<NotificationUnreadCount>(
    "/notifications/unread-count"
  )

  return data.count
}

export async function markRead(id: string): Promise<AppNotification> {
  const { data } = await api.post<ApiEnvelope<AppNotification>>(
    `/notifications/${encodeURIComponent(id)}/read`
  )

  return data.data
}

export async function markAllRead(): Promise<number> {
  const { data } = await api.post<ApiEnvelope<MarkAllReadResult>>(
    "/notifications/read-all"
  )

  return data.data.updated
}
