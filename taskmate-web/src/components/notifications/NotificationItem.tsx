import { cn } from "@/lib/utils"
import { formatRelativeDate } from "@/lib/format"
import { notificationTitle } from "@/lib/notifications"
import type { AppNotification } from "@/types"

export function NotificationItem({
  notification,
  onSelect,
  compact = false,
}: {
  notification: AppNotification
  onSelect: () => void
  compact?: boolean
}) {
  const unread = notification.read_at === null
  const title = notificationTitle(notification)
  const time = formatRelativeDate(notification.created_at)

  return (
    <button
      type="button"
      onClick={onSelect}
      aria-label={`${title}${unread ? ", unread" : ""}${time ? `, ${time}` : ""}`}
      className={cn(
        "flex w-full gap-2.5 rounded-lg border text-left transition-colors outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50",
        compact ? "px-2 py-2" : "px-3 py-3",
        unread
          ? "border-primary/20 bg-primary/5 hover:bg-primary/10"
          : "border-border bg-card hover:bg-muted/50"
      )}
    >
      <span
        aria-hidden="true"
        className={cn(
          "mt-1.5 size-2 shrink-0 rounded-full",
          unread ? "bg-primary" : "bg-transparent"
        )}
      />
      <span className="min-w-0 flex-1 space-y-0.5">
        <span className="flex items-start justify-between gap-2">
          <span className="truncate text-sm font-medium text-foreground">
            {title}
          </span>
          {time ? (
            <span className="shrink-0 text-xs text-muted-foreground">{time}</span>
          ) : null}
        </span>
        {notification.body ? (
          <span className="block text-xs text-muted-foreground line-clamp-2">
            {notification.body}
          </span>
        ) : null}
      </span>
    </button>
  )
}
