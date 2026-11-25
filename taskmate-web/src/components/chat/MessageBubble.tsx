import { formatTime } from "@/lib/format"
import { useAuthStore } from "@/stores/useAuthStore"
import type { Message } from "@/types"

export function MessageBubble({ message }: { message: Message }) {
  const ownId = useAuthStore((state) => state.user?.id)
  const isOwn = message.pending || message.sender_id === ownId

  return (
    <div className={`flex flex-col ${isOwn ? "items-end" : "items-start"}`}>
      <div
        className={[
          "max-w-[85%] whitespace-pre-wrap break-words rounded-lg px-3 py-2 text-sm sm:max-w-[75%]",
          isOwn
            ? "bg-primary text-primary-foreground"
            : "border border-border bg-muted text-foreground",
          message.pending ? "opacity-70" : "",
        ].join(" ")}
      >
        {message.body}
      </div>
      <span className="mt-1 px-1 text-[11px] text-muted-foreground">
        {message.pending ? "Sending..." : formatTime(message.created_at)}
      </span>
    </div>
  )
}
