import { useEffect } from "react"
import { ArrowLeft, MessagesSquare } from "lucide-react"
import { Link, useParams } from "react-router-dom"

import { ConversationList } from "@/components/chat/ConversationList"
import { MessageThread } from "@/components/chat/MessageThread"
import { ErrorState } from "@/components/PageStates"
import { Card, CardContent } from "@/components/ui/card"
import { Skeleton } from "@/components/ui/skeleton"
import { useChatStore } from "@/stores/useChatStore"

function ThreadSkeleton() {
  return (
    <div className="flex h-[calc(100dvh-14rem)] min-h-[420px] sm:h-[calc(100vh-12rem)] flex-col overflow-hidden rounded-lg border border-border bg-card text-sm shadow-sm">
      <div className="flex items-center gap-3 border-b border-border p-3">
        <Skeleton className="size-10 rounded-full" />
        <div className="space-y-2">
          <Skeleton className="h-3.5 w-32" />
          <Skeleton className="h-3 w-44" />
        </div>
      </div>
      <div className="flex-1 space-y-3 p-3">
        <Skeleton className="ml-auto h-12 w-2/3 rounded-lg" />
        <Skeleton className="mr-auto h-16 w-1/2 rounded-lg" />
        <Skeleton className="ml-auto h-10 w-1/3 rounded-lg" />
      </div>
      <div className="border-t border-border p-3">
        <Skeleton className="h-20 w-full rounded-lg" />
      </div>
    </div>
  )
}

export function MessagesPage() {
  const { conversationId } = useParams<{ conversationId: string }>()
  const parsedId = conversationId ? Number(conversationId) : null
  const activeId = parsedId !== null && Number.isFinite(parsedId) ? parsedId : null

  const conversations = useChatStore((state) => state.conversations)
  const activeConversation = useChatStore((state) => state.activeConversation)
  const loading = useChatStore((state) => state.conversationsLoading)
  const error = useChatStore((state) => state.conversationsError)
  const fetchConversations = useChatStore((state) => state.fetchConversations)
  const fetchUnreadCount = useChatStore((state) => state.fetchUnreadCount)

  useEffect(() => {
    void fetchConversations()
    void fetchUnreadCount()
  }, [fetchConversations, fetchUnreadCount])

  const active =
    activeId !== null
      ? conversations.find((conversation) => conversation.id === activeId) ??
        (activeConversation?.id === activeId ? activeConversation : undefined)
      : undefined

  const showThreadOnMobile = activeId !== null

  return (
    <div className="space-y-4">
      <div className="space-y-1">
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">
          Messages
        </h1>
        <p className="text-sm text-muted-foreground">
          Discuss task details with uploaders and applicants.
        </p>
      </div>

      <div className="grid gap-4 md:grid-cols-[320px_1fr] md:items-start">
        <div
          className={`min-w-0 ${showThreadOnMobile ? "hidden md:block" : ""}`}
        >
          <ConversationList activeId={activeId} />
        </div>

        <div
          className={`min-w-0 ${showThreadOnMobile ? "" : "hidden md:block"}`}
        >
          {activeId === null ? (
            <Card className="flex h-[calc(100dvh-14rem)] min-h-[420px] sm:h-[calc(100vh-12rem)] items-center justify-center">
              <div className="flex flex-col items-center gap-3 px-6 text-center">
                <span className="flex size-12 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                  <MessagesSquare className="size-6" />
                </span>
                <div className="space-y-1">
                  <p className="font-medium text-foreground">
                    Select a conversation
                  </p>
                  <p className="text-sm text-muted-foreground">
                    Choose a conversation from the list to start messaging.
                  </p>
                </div>
              </div>
            </Card>
          ) : error && conversations.length === 0 ? (
            <ErrorState
              title="Could not load conversations"
              message={error}
              onRetry={() => void fetchConversations()}
            />
          ) : loading && conversations.length === 0 ? (
            <ThreadSkeleton />
          ) : active ? (
            <MessageThread conversation={active} />
          ) : (
            <Card className="flex h-[calc(100dvh-14rem)] min-h-[420px] sm:h-[calc(100vh-12rem)] items-center justify-center">
              <CardContent className="flex flex-col items-center gap-3 text-center">
                <p className="font-medium text-foreground">Conversation not found</p>
                <p className="text-sm text-muted-foreground">
                  It may have been removed, or it does not belong to you.
                </p>
                <Link
                  to="/messages"
                  className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                >
                  <ArrowLeft className="size-4" />
                  Back to messages
                </Link>
              </CardContent>
            </Card>
          )}
        </div>
      </div>
    </div>
  )
}
