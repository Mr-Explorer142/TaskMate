import { MessagesSquare } from "lucide-react"

import { ConversationItem } from "@/components/chat/ConversationItem"
import { EmptyState, ErrorState } from "@/components/PageStates"
import { Card, CardContent } from "@/components/ui/card"
import { Skeleton } from "@/components/ui/skeleton"
import { usePolling } from "@/hooks/usePolling"
import { useChatStore } from "@/stores/useChatStore"

export function ConversationList({ activeId }: { activeId: number | null }) {
  const conversations = useChatStore((state) => state.conversations)
  const loading = useChatStore((state) => state.conversationsLoading)
  const error = useChatStore((state) => state.conversationsError)
  const fetchConversations = useChatStore((state) => state.fetchConversations)

  usePolling(() => fetchConversations({ silent: true }), 10_000)

  return (
    <Card size="sm" className="overflow-hidden">
      <CardContent className="space-y-1 p-2">
        {loading && conversations.length === 0 ? (
          <div className="space-y-2 p-1">
            {Array.from({ length: 4 }).map((_, index) => (
              <div key={index} className="flex items-center gap-3 py-2">
                <Skeleton className="size-10 rounded-full" />
                <div className="flex-1 space-y-2">
                  <Skeleton className="h-3.5 w-1/2" />
                  <Skeleton className="h-3 w-3/4" />
                </div>
              </div>
            ))}
          </div>
        ) : error && conversations.length === 0 ? (
          <ErrorState
            title="Could not load conversations"
            message={error}
            onRetry={() => void fetchConversations()}
          />
        ) : conversations.length === 0 ? (
          <EmptyState
            icon={MessagesSquare}
            title="No conversations yet"
            description="Message a task uploader or an applicant to start a conversation."
          />
        ) : (
          conversations.map((conversation) => (
            <ConversationItem
              key={conversation.id}
              conversation={conversation}
              active={conversation.id === activeId}
            />
          ))
        )}
      </CardContent>
    </Card>
  )
}
