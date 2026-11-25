import { useEffect, useState } from "react"
import { Bell, CheckCheck, Loader2 } from "lucide-react"
import { useNavigate, useSearchParams } from "react-router-dom"
import { toast } from "sonner"

import { getApiErrorMessage } from "@/api/client"
import { NotificationItem } from "@/components/notifications/NotificationItem"
import { EmptyState, ErrorState } from "@/components/PageStates"
import { Pagination } from "@/components/tasks/Pagination"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Skeleton } from "@/components/ui/skeleton"
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { usePageTitle } from "@/hooks/usePageTitle"
import { notificationTarget } from "@/lib/notifications"
import { useAuthStore } from "@/stores/useAuthStore"
import { useNotificationStore } from "@/stores/useNotificationStore"
import type { AppNotification } from "@/types"

type NotificationTab = "all" | "unread"

export function NotificationsPage() {
  usePageTitle("Notifications")

  const [searchParams, setSearchParams] = useSearchParams()
  const [busy, setBusy] = useState(false)
  const navigate = useNavigate()
  const currentUserId = useAuthStore((state) => state.user?.id ?? null)
  const notifications = useNotificationStore((state) => state.notifications)
  const meta = useNotificationStore((state) => state.meta)
  const loading = useNotificationStore((state) => state.loading)
  const error = useNotificationStore((state) => state.error)
  const unreadCount = useNotificationStore((state) => state.unreadCount)
  const fetchList = useNotificationStore((state) => state.fetchList)
  const fetchUnreadCount = useNotificationStore((state) => state.fetchUnreadCount)
  const markRead = useNotificationStore((state) => state.markRead)
  const markAllRead = useNotificationStore((state) => state.markAllRead)

  const tab: NotificationTab = searchParams.get("tab") === "unread" ? "unread" : "all"
  const page = Math.max(1, Number(searchParams.get("page")) || 1)
  const unread = tab === "unread"

  useEffect(() => {
    void fetchList({ page, unread })
  }, [fetchList, page, unread])

  function changeTab(nextTab: NotificationTab) {
    const nextParams = new URLSearchParams(searchParams)
    nextParams.set("tab", nextTab)
    nextParams.delete("page")
    setSearchParams(nextParams)
  }

  function changePage(nextPage: number) {
    const nextParams = new URLSearchParams(searchParams)
    nextParams.set("page", String(nextPage))
    setSearchParams(nextParams)
  }

  async function handleSelect(notification: AppNotification) {
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
      await Promise.all([
        fetchList({ page, unread }, { silent: true }),
        fetchUnreadCount(),
      ])
    } catch (caught) {
      toast.error(getApiErrorMessage(caught))
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="space-y-1">
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">
            Notifications
          </h1>
          <p className="text-sm text-muted-foreground">
            Updates from your tasks, teams, and reviews.
          </p>
        </div>
        <Button
          variant="outline"
          size="sm"
          disabled={busy || unreadCount === 0}
          onClick={() => void handleMarkAllRead()}
        >
          {busy ? <Loader2 className="size-4 animate-spin" /> : <CheckCheck className="size-4" />}
          Mark all as read
        </Button>
      </div>

      <Tabs value={tab} onValueChange={(value) => changeTab(value as NotificationTab)}>
        <TabsList>
          <TabsTrigger value="all">All</TabsTrigger>
          <TabsTrigger value="unread">Unread</TabsTrigger>
        </TabsList>
      </Tabs>

      <Card>
        <CardContent className="space-y-4 pt-4">
          {loading && notifications.length === 0 ? (
            <div className="space-y-3">
              {[0, 1, 2, 3, 4].map((index) => (
                <Skeleton key={index} className="h-16 w-full rounded-lg" />
              ))}
            </div>
          ) : error ? (
            <ErrorState
              title="Could not load notifications"
              message={error}
              onRetry={() => void fetchList({ page, unread })}
            />
          ) : notifications.length === 0 ? (
            <EmptyState
              icon={Bell}
              title={unread ? "No unread notifications" : "No notifications yet"}
              description={
                unread
                  ? "You are all caught up. New updates will appear here."
                  : "Applications, team changes, and reviews will appear here."
              }
            />
          ) : (
            <>
              <div className="space-y-2">
                {notifications.map((notification) => (
                  <NotificationItem
                    key={notification.id}
                    notification={notification}
                    onSelect={() => void handleSelect(notification)}
                  />
                ))}
              </div>
              {meta && meta.last_page > 1 ? (
                <Pagination
                  page={meta.current_page}
                  lastPage={meta.last_page}
                  onPageChange={changePage}
                  disabled={loading}
                />
              ) : null}
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
