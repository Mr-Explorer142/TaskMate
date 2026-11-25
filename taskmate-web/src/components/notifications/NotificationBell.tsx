import { useState } from "react"
import { ArrowRight, Bell, CheckCheck, Loader2 } from "lucide-react"
import { Link, useNavigate } from "react-router-dom"
import { toast } from "sonner"

import { getApiErrorMessage } from "@/api/client"
import { NotificationItem } from "@/components/notifications/NotificationItem"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { usePolling } from "@/hooks/usePolling"
import { notificationTarget } from "@/lib/notifications"
import { useAuthStore } from "@/stores/useAuthStore"
import { useNotificationStore } from "@/stores/useNotificationStore"
import type { AppNotification } from "@/types"

export function NotificationBell() {
  const [open, setOpen] = useState(false)
  const [busy, setBusy] = useState(false)
  const navigate = useNavigate()
  const currentUserId = useAuthStore((state) => state.user?.id ?? null)
  const notifications = useNotificationStore((state) => state.notifications)
  const unreadCount = useNotificationStore((state) => state.unreadCount)
  const loading = useNotificationStore((state) => state.loading)
  const error = useNotificationStore((state) => state.error)
  const fetchList = useNotificationStore((state) => state.fetchList)
  const fetchUnreadCount = useNotificationStore((state) => state.fetchUnreadCount)
  const markRead = useNotificationStore((state) => state.markRead)
  const markAllRead = useNotificationStore((state) => state.markAllRead)

  usePolling(() => fetchUnreadCount(), 20_000)

  function handleOpenChange(nextOpen: boolean) {
    setOpen(nextOpen)

    if (nextOpen) {
      void fetchList({}, { silent: true })
      void fetchUnreadCount()
    }
  }

  async function handleSelect(notification: AppNotification) {
    setOpen(false)

    try {
      await markRead(notification.id)
    } catch (caught) {
      toast.error(getApiErrorMessage(caught))
    }

    const target = notificationTarget(notification, currentUserId)

    if (target) {
      navigate(target)
    }
  }

  async function handleMarkAllRead() {
    setBusy(true)

    try {
      await markAllRead()
      toast.success("All notifications marked as read.")
    } catch (caught) {
      toast.error(getApiErrorMessage(caught))
    } finally {
      setBusy(false)
    }
  }

  return (
    <DropdownMenu open={open} onOpenChange={handleOpenChange}>
      <DropdownMenuTrigger asChild>
        <Button
          variant="ghost"
          size="icon"
          className="relative"
          aria-label={
            unreadCount > 0
              ? `Notifications, ${unreadCount} unread`
              : "Notifications"
          }
        >
          <Bell className="size-4" />
          {unreadCount > 0 ? (
            <span
              aria-hidden="true"
              className="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold text-primary-foreground"
            >
              {unreadCount > 99 ? "99+" : unreadCount}
            </span>
          ) : null}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-80 p-2 sm:w-96">
        <div className="flex items-center justify-between gap-2 px-2 py-1.5">
          <DropdownMenuLabel className="p-0">Notifications</DropdownMenuLabel>
          <DropdownMenuItem
            asChild
            disabled={busy || unreadCount === 0}
            className="px-1.5 py-1 text-xs"
          >
            <button
              type="button"
              disabled={busy || unreadCount === 0}
              onClick={() => void handleMarkAllRead()}
            >
              {busy ? (
                <Loader2 className="size-3.5 animate-spin" />
              ) : (
                <CheckCheck className="size-3.5" />
              )}
              Mark all as read
            </button>
          </DropdownMenuItem>
        </div>
        <DropdownMenuSeparator />

        <div className="max-h-80 overflow-y-auto py-1">
          {loading && notifications.length === 0 ? (
            <div className="space-y-2 px-1">
              {[0, 1, 2].map((index) => (
                <Skeleton key={index} className="h-14 w-full rounded-lg" />
              ))}
            </div>
          ) : error ? (
            <p className="px-2 py-6 text-center text-sm text-muted-foreground">
              {error}
            </p>
          ) : notifications.length === 0 ? (
            <p className="px-2 py-6 text-center text-sm text-muted-foreground">
              You are all caught up.
            </p>
          ) : (
            <div className="space-y-1">
              {notifications.slice(0, 8).map((notification) => (
                <NotificationItem
                  key={notification.id}
                  notification={notification}
                  onSelect={() => void handleSelect(notification)}
                  compact
                />
              ))}
            </div>
          )}
        </div>

        <DropdownMenuSeparator />
        <DropdownMenuItem asChild className="justify-center">
          <Link to="/notifications" onClick={() => setOpen(false)}>
            View all
            <ArrowRight className="size-4" />
          </Link>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
