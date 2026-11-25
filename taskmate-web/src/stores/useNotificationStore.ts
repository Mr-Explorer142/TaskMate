import { create } from "zustand"

import { getApiErrorMessage } from "@/api/client"
import {
  list as listRequest,
  markAllRead as markAllReadRequest,
  markRead as markReadRequest,
  unreadCount as unreadCountRequest,
} from "@/api/notifications"
import type {
  AppNotification,
  NotificationListFilters,
  PaginationMeta,
} from "@/types"

interface NotificationState {
  notifications: AppNotification[]
  meta: PaginationMeta | null
  loading: boolean
  refreshing: boolean
  error: string | null
  unreadCount: number
  fetchList: (
    filters?: NotificationListFilters,
    options?: { silent?: boolean }
  ) => Promise<void>
  fetchUnreadCount: () => Promise<void>
  markRead: (id: string) => Promise<void>
  markAllRead: () => Promise<void>
  reset: () => void
}

let listVersion = 0

export const useNotificationStore = create<NotificationState>((set, get) => ({
  notifications: [],
  meta: null,
  loading: false,
  refreshing: false,
  error: null,
  unreadCount: 0,

  async fetchList(filters = {}, options = {}) {
    const silent = options.silent ?? false
    const request = ++listVersion

    if (silent) {
      set({ refreshing: true })
    } else {
      set({ loading: true, error: null })
    }

    try {
      const page = await listRequest(filters)

      if (request !== listVersion) {
        return
      }

      set({
        notifications: page.data,
        meta: page.meta,
        loading: false,
        refreshing: false,
        error: null,
      })
    } catch (error) {
      if (request !== listVersion) {
        return
      }

      set({
        loading: false,
        refreshing: false,
        error: silent ? get().error : getApiErrorMessage(error),
      })
    }
  },

  async fetchUnreadCount() {
    try {
      set({ unreadCount: await unreadCountRequest() })
    } catch {
      // Background failures keep the last known count visible.
    }
  },

  async markRead(id) {
    const current = get().notifications.find((item) => item.id === id)

    if (!current || current.read_at) {
      return
    }

    const readAt = new Date().toISOString()

    set((state) => ({
      notifications: state.notifications.map((item) =>
        item.id === id ? { ...item, read_at: readAt } : item
      ),
      unreadCount: Math.max(0, state.unreadCount - 1),
    }))

    try {
      await markReadRequest(id)
    } catch (error) {
      set((state) => ({
        notifications: state.notifications.map((item) =>
          item.id === id ? { ...item, read_at: current.read_at } : item
        ),
        unreadCount: state.unreadCount + 1,
      }))

      throw error
    }
  },

  async markAllRead() {
    const previous = get().notifications
    const previousCount = get().unreadCount
    const readAt = new Date().toISOString()

    set((state) => ({
      notifications: state.notifications.map((item) => ({
        ...item,
        read_at: item.read_at ?? readAt,
      })),
      unreadCount: 0,
    }))

    try {
      await markAllReadRequest()
    } catch (error) {
      set({ notifications: previous, unreadCount: previousCount })
      throw error
    }
  },

  reset() {
    listVersion++
    set({
      notifications: [],
      meta: null,
      loading: false,
      refreshing: false,
      error: null,
      unreadCount: 0,
    })
  },
}))
