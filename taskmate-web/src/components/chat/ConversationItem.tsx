import { Link } from "react-router-dom"

import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { formatRelativeDate } from "@/lib/format"
import { getInitials } from "@/lib/utils"
import { useAuthStore } from "@/stores/useAuthStore"
import type { Conversation } from "@/types"

export function ConversationItem({
  conversation,
  active,
}: {
  conversation: Conversation
  active: boolean
}) {
  const ownId = useAuthStore((state) => state.user?.id)
  const other = conversation.other_user
  const lastMessage = conversation.last_message
  const preview = lastMessage
    ? `${lastMessage.sender_id === ownId ? "You: " : ""}${lastMessage.body}`
    : "No messages yet"

  return (
    <Link
      to={`/messages/${conversation.id}`}
      className={[
        "flex items-start gap-3 rounded-lg border px-3 py-3 transition-colors",
        active
          ? "border-primary/30 bg-secondary"
          : "border-transparent hover:bg-muted",
      ].join(" ")}
    >
      <Avatar className="size-10 shrink-0">
        {other?.avatar_url ? (
          <AvatarImage src={other.avatar_url} alt={other.name} />
        ) : null}
        <AvatarFallback className="bg-secondary text-xs font-semibold text-secondary-foreground">
          {getInitials(other?.name ?? "TaskMate")}
        </AvatarFallback>
      </Avatar>

      <div className="min-w-0 flex-1">
        <div className="flex items-baseline justify-between gap-2">
          <p className="truncate text-sm font-semibold text-foreground">
            {other?.name ?? "Unknown user"}
          </p>
          {lastMessage ? (
            <span className="shrink-0 text-[11px] text-muted-foreground">
              {formatRelativeDate(lastMessage.created_at)}
            </span>
          ) : null}
        </div>
        <p className="truncate text-xs text-muted-foreground">
          {conversation.task?.title ?? "Task"}
        </p>
        <div className="mt-1 flex min-w-0 items-center justify-between gap-2">
          <p className="truncate text-sm text-muted-foreground">{preview}</p>
          {conversation.unread_count > 0 ? (
            <span className="flex min-w-5 shrink-0 items-center justify-center rounded-full bg-primary px-1.5 text-[11px] font-semibold text-primary-foreground">
              {conversation.unread_count > 99 ? "99+" : conversation.unread_count}
            </span>
          ) : null}
        </div>
      </div>
    </Link>
  )
}
