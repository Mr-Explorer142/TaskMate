import { useEffect, useRef } from "react"
import { ArrowLeft, MessagesSquare } from "lucide-react"
import { Link } from "react-router-dom"
import { toast } from "sonner"

import { getApiErrorMessage } from "@/api/client"
import { MessageBubble } from "@/components/chat/MessageBubble"
import { MessageInput } from "@/components/chat/MessageInput"
import { EmptyState, ErrorState } from "@/components/PageStates"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { usePolling } from "@/hooks/usePolling"
import { formatDayLabel, isNewMessageDay } from "@/lib/format"
import { getInitials } from "@/lib/utils"
import { useChatStore } from "@/stores/useChatStore"
import type { Conversation } from "@/types"


export function MessageThread({ conversation }: { conversation: Conversation }) {
  const messages = useChatStore((state) => state.messages)
  const loading = useChatStore((state) => state.messagesLoading)
  const error = useChatStore((state) => state.messagesError)
  const loadingOlder = useChatStore((state) => state.loadingOlder)
  const hasMoreHistory = useChatStore((state) => state.hasMoreHistory)
  const sending = useChatStore((state) => state.sending)
  const openConversation = useChatStore((state) => state.openConversation)
  const closeConversation = useChatStore((state) => state.closeConversation)
  const pollMessages = useChatStore((state) => state.pollMessages)
  const loadOlder = useChatStore((state) => state.loadOlder)
  const sendMessage = useChatStore((state) => state.sendMessage)

  const scrollRef = useRef<HTMLDivElement>(null)
  const shouldStick = useRef(true)
  const lastCount = useRef(0)
  const other = conversation.other_user

  useEffect(() => {
    lastCount.current = 0
    shouldStick.current = true
    void openConversation(conversation.id)

    return () => closeConversation()
  }, [conversation.id, openConversation, closeConversation])

  usePolling(() => pollMessages(conversation.id), 4_000)

  useEffect(() => {
    if (loading) {
      return
    }

    const grew = messages.length > lastCount.current
    lastCount.current = messages.length

    if (grew && shouldStick.current && scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight
    }
  }, [messages, loading])

  async function handleLoadOlder() {
    const container = scrollRef.current
    const previousHeight = container?.scrollHeight ?? 0
    const previousTop = container?.scrollTop ?? 0

    try {
      await loadOlder(conversation.id)

      requestAnimationFrame(() => {
        if (container) {
          container.scrollTop = container.scrollHeight - previousHeight + previousTop
        }
      })
    } catch (caught) {
      toast.error(getApiErrorMessage(caught))
    }
  }

  async function handleSend(body: string) {
    try {
      await sendMessage(conversation.id, body)
    } catch (caught) {
      toast.error(getApiErrorMessage(caught))
      throw caught
    }
  }

  return (
    <div className="flex h-[calc(100dvh-14rem)] min-h-[420px] flex-col overflow-hidden rounded-lg border border-border bg-card text-sm text-card-foreground shadow-sm sm:h-[calc(100vh-12rem)]">
      <div className="flex items-center gap-3 border-b border-border p-3">
        <Button variant="ghost" size="icon" asChild className="md:hidden">
          <Link to="/messages" aria-label="Back to conversations">
            <ArrowLeft className="size-4" />
          </Link>
        </Button>

        <Avatar className="size-10 shrink-0">
          {other?.avatar_url ? (
            <AvatarImage src={other.avatar_url} alt={other.name} />
          ) : null}
          <AvatarFallback className="bg-secondary text-xs font-semibold text-secondary-foreground">
            {getInitials(other?.name ?? "TaskMate")}
          </AvatarFallback>
        </Avatar>

        <div className="min-w-0">
          <p className="truncate text-sm font-semibold text-foreground">
            {other?.name ?? "Unknown user"}
          </p>
          {conversation.task ? (
            <Link
              to={`/tasks/${conversation.task.id}`}
              className="truncate text-xs text-muted-foreground hover:text-primary hover:underline"
            >
              {conversation.task.title}
            </Link>
          ) : (
            <p className="text-xs text-muted-foreground">Task</p>
          )}
        </div>
      </div>

      <div
        ref={scrollRef}
        onScroll={() => {
          const container = scrollRef.current

          if (!container) {
            return
          }

          shouldStick.current =
            container.scrollHeight - container.scrollTop - container.clientHeight < 80
        }}
        className="flex-1 space-y-3 overflow-y-auto px-3 py-4"
      >
        {loading ? (
          <div className="space-y-3">
            {Array.from({ length: 5 }).map((_, index) => (
              <Skeleton
                key={index}
                className={[
                  "h-12 w-2/3 rounded-lg",
                  index % 2 === 0 ? "mr-auto" : "ml-auto",
                ].join(" ")}
              />
            ))}
          </div>
        ) : error ? (
          <ErrorState
            title="Could not load messages"
            message={error}
            onRetry={() => void openConversation(conversation.id)}
          />
        ) : messages.length === 0 ? (
          <EmptyState
            icon={MessagesSquare}
            title="No messages yet"
            description="Say hello to start the conversation."
          />
        ) : (
          <>
            {hasMoreHistory ? (
              <div className="flex justify-center">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => void handleLoadOlder()}
                  disabled={loadingOlder}
                >
                  {loadingOlder ? "Loading..." : "Load older"}
                </Button>
              </div>
            ) : null}

            {messages.map((message, index) => {
              const previous = index > 0 ? messages[index - 1] : undefined

              return (
                <div key={message.id} className="space-y-3">
                  {isNewMessageDay(message, previous) ? (
                    <div className="flex items-center gap-3">
                      <span className="h-px flex-1 bg-border" />
                      <span className="text-[11px] font-medium text-muted-foreground">
                        {formatDayLabel(message.created_at)}
                      </span>
                      <span className="h-px flex-1 bg-border" />
                    </div>
                  ) : null}
                  <MessageBubble message={message} />
                </div>
              )
            })}

          </>
        )}
      </div>

      <div className="border-t border-border p-3">
        <MessageInput sending={sending} onSend={handleSend} />
      </div>
    </div>
  )
}
