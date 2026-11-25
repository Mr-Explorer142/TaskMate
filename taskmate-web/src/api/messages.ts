import { api } from "@/api/client"
import type { ApiEnvelope, Conversation, Message, Paginated } from "@/types"

export interface MessageQuery {
  before_id?: number
  after_id?: number
}

export async function createConversation(
  taskId: number,
  userId: number
): Promise<Conversation> {
  const { data } = await api.post<ApiEnvelope<Conversation>>("/conversations", {
    task_id: taskId,
    user_id: userId,
  })

  return data.data
}

export async function listConversations(): Promise<Paginated<Conversation>> {
  const { data } = await api.get<Paginated<Conversation>>("/conversations")
  return data
}

export async function unreadCount(): Promise<number> {
  const { data } = await api.get<ApiEnvelope<{ count: number }>>(
    "/conversations/unread-count"
  )

  return data.data.count
}

export async function getMessages(
  conversationId: number,
  query: MessageQuery = {}
): Promise<Message[]> {
  const { data } = await api.get<{ data: Message[] }>(
    `/conversations/${conversationId}/messages`,
    { params: query }
  )

  return data.data
}

export async function sendMessage(
  conversationId: number,
  body: string
): Promise<Message> {
  const { data } = await api.post<ApiEnvelope<Message>>(
    `/conversations/${conversationId}/messages`,
    { body }
  )

  return data.data
}

export async function markRead(conversationId: number): Promise<number> {
  const { data } = await api.post<ApiEnvelope<{ updated: number }>>(
    `/conversations/${conversationId}/read`
  )

  return data.data.updated
}
