import { create } from "zustand"

import { getApiErrorMessage } from "@/api/client"
import {
  deleteMessage as deleteMessageRequest,
  getMessages,
  listConversations,
  markRead as markReadRequest,
  sendMessage as sendMessageRequest,
  unreadCount as unreadCountRequest,
  updateMessage as updateMessageRequest,
} from "@/api/messages"
import type {
  Conversation,
  Message,
  MessageDeleteScope,
  MessageReplyPreview,
  PaginationMeta,
} from "@/types"

interface ChatState {
  conversations: Conversation[]
  conversationsMeta: PaginationMeta | null
  conversationsLoading: boolean
  conversationsRefreshing: boolean
  conversationsError: string | null
  unreadCount: number

  activeId: number | null
  activeConversation: Conversation | null
  messages: Message[]
  messagesLoading: boolean
  messagesError: string | null
  loadingOlder: boolean
  hasMoreHistory: boolean
  sending: boolean

  upsertConversation: (conversation: Conversation) => void
  fetchConversations: (options?: { silent?: boolean }) => Promise<void>
  fetchUnreadCount: () => Promise<void>
  openConversation: (id: number) => Promise<void>
  closeConversation: () => void
  pollMessages: (id: number) => Promise<void>
  loadOlder: (id: number) => Promise<void>
  sendMessage: (id: number, body: string, replyToId?: number | null) => Promise<Message>
  updateMessage: (id: number, messageId: number, body: string) => Promise<Message>
  deleteMessage: (id: number, messageId: number, scope: MessageDeleteScope) => Promise<Message>
  markRead: (id: number) => Promise<void>
  reset: () => void
}

const DELETED_BODY = "This message was deleted."

let threadRequest = 0

function mergeMessages(messages: Message[]): Message[] {
  const seen = new Map<number, Message>()

  for (const message of messages) {
    if (!seen.has(message.id)) {
      seen.set(message.id, message)
    }
  }

  return [...seen.values()].sort((a, b) => a.id - b.id)
}

function previewOf(body: string): string {
  return body.length > 120 ? `${body.slice(0, 117)}...` : body
}

function messagePreview(message: Message) {
  return {
    id: message.id,
    sender_id: message.sender_id,
    body: previewOf(message.body),
    created_at: message.created_at,
    is_deleted_for_everyone: message.is_deleted_for_everyone,
  }
}

function replyPreviewOf(message: Message): MessageReplyPreview {
  return {
    id: message.id,
    sender_id: message.sender_id,
    body: previewOf(message.body),
    created_at: message.created_at,
    is_deleted_for_everyone: message.is_deleted_for_everyone,
  }
}

function applyMessageChange(messages: Message[], updated: Message): Message[] {
  return messages.map((message) => {
    if (message.id === updated.id) {
      return updated
    }

    if (message.reply_to_id === updated.id && message.reply_to) {
      return {
        ...message,
        reply_to: {
          ...message.reply_to,
          body: previewOf(updated.body),
          is_deleted_for_everyone: updated.is_deleted_for_everyone,
        },
      }
    }

    return message
  })
}

function moveConversationToTop(
  conversations: Conversation[],
  id: number,
  update: (conversation: Conversation) => Conversation
): Conversation[] {
  const target = conversations.find((conversation) => conversation.id === id)

  if (!target) {
    return conversations
  }

  const updated = update(target)

  return [updated, ...conversations.filter((conversation) => conversation.id !== id)]
}

function restoreMessage(
  messages: Message[],
  message: Message,
  previousIndex: number
): Message[] {
  if (messages.some((item) => item.id === message.id)) {
    return messages.map((item) => (item.id === message.id ? message : item))
  }

  const insertAt = Math.min(Math.max(previousIndex, 0), messages.length)

  return [...messages.slice(0, insertAt), message, ...messages.slice(insertAt)]
}

function withPreview(
  state: ChatState,
  id: number,
  message: Message,
  moveToTop = true
) {
  const preview = messagePreview(message)
  const update = (conversation: Conversation) => ({
    ...conversation,
    last_message: preview,
    updated_at: moveToTop ? message.created_at : conversation.updated_at,
  })

  return {
    conversations: moveToTop
      ? moveConversationToTop(state.conversations, id, update)
      : state.conversations.map((conversation) =>
          conversation.id === id ? update(conversation) : conversation
        ),
    activeConversation:
      state.activeConversation?.id === id
        ? update(state.activeConversation)
        : state.activeConversation,
  }
}

function tombstoneOf(message: Message, scope: MessageDeleteScope): Message {
  return {
    ...message,
    body: DELETED_BODY,
    is_deleted_for_everyone: scope === "everyone" || message.is_deleted_for_everyone,
    is_deleted_for_me: scope === "me" || message.is_deleted_for_me,
  }
}

export const useChatStore = create<ChatState>((set, get) => ({
  conversations: [],
  conversationsMeta: null,
  conversationsLoading: false,
  conversationsRefreshing: false,
  conversationsError: null,
  unreadCount: 0,

  activeId: null,
  activeConversation: null,
  messages: [],
  messagesLoading: false,
  messagesError: null,
  loadingOlder: false,
  hasMoreHistory: false,
  sending: false,

  upsertConversation(conversation) {
    set((state) => {
      const index = state.conversations.findIndex(
        (item) => item.id === conversation.id
      )
      const conversations =
        index === -1
          ? [conversation, ...state.conversations]
          : state.conversations.map((item, position) =>
              position === index ? { ...item, ...conversation } : item
            )

      return { conversations, activeConversation: conversation }
    })
  },

  async fetchConversations(options = {}) {
    const silent = options.silent ?? false

    if (silent) {
      set({ conversationsRefreshing: true })
    } else {
      set({ conversationsLoading: true, conversationsError: null })
    }

    try {
      const page = await listConversations()

      set((state) => {
        const active = state.activeConversation
        const refreshed = active
          ? page.data.find((conversation) => conversation.id === active.id)
          : undefined

        return {
          conversations:
            refreshed || !active ? page.data : [...page.data, active],
          conversationsMeta: page.meta,
          conversationsLoading: false,
          conversationsRefreshing: false,
          conversationsError: null,
          activeConversation: refreshed ?? active ?? null,
        }
      })
    } catch (error) {
      if (silent) {
        set({ conversationsRefreshing: false })
      } else {
        set({
          conversationsLoading: false,
          conversationsError: getApiErrorMessage(error),
        })
      }
    }
  },

  async fetchUnreadCount() {
    try {
      const count = await unreadCountRequest()
      set({ unreadCount: count })
    } catch {
      // Ignore background failures; the badge keeps its last known value.
    }
  },

  async openConversation(id) {
    const request = ++threadRequest
    const existing = get().conversations.find(
      (conversation) => conversation.id === id
    )

    set({
      activeId: id,
      activeConversation: existing ?? null,
      messages: [],
      messagesLoading: true,
      messagesError: null,
      hasMoreHistory: false,
      loadingOlder: false,
    })

    try {
      const messages = await getMessages(id)

      if (request !== threadRequest) {
        return
      }

      set({
        messages,
        messagesLoading: false,
        hasMoreHistory: messages.length >= 30,
      })

      if (messages.length > 0) {
        void get().markRead(id)
      }
    } catch (error) {
      if (request !== threadRequest) {
        return
      }

      set({
        messagesLoading: false,
        messagesError: getApiErrorMessage(error),
      })
    }
  },

  closeConversation() {
    threadRequest++
    set({
      activeId: null,
      activeConversation: null,
      messages: [],
      messagesLoading: false,
      messagesError: null,
      loadingOlder: false,
      hasMoreHistory: false,
    })
  },

  async pollMessages(id) {
    if (get().activeId !== id || get().sending) {
      return
    }

    const current = get().messages
    const lastReal = [...current].reverse().find((message) => message.id > 0)

    try {
      const incoming = await getMessages(
        id,
        lastReal ? { after_id: lastReal.id } : {}
      )

      if (get().activeId !== id || incoming.length === 0) {
        return
      }

      const fresh = incoming.filter(
        (message) => !get().messages.some((existing) => existing.id === message.id)
      )

      if (fresh.length === 0) {
        return
      }

      set((state) => ({ messages: mergeMessages([...state.messages, ...fresh]) }))

      const latest = fresh[fresh.length - 1]

      set((state) => withPreview(state, id, latest))

      void get().markRead(id)
    } catch {
      // Polling failures are transient; the next tick retries.
    }
  },

  async loadOlder(id) {
    const state = get()
    const firstReal = state.messages.find((message) => message.id > 0)

    if (!firstReal || state.loadingOlder) {
      return
    }

    set({ loadingOlder: true })

    try {
      const older = await getMessages(id, { before_id: firstReal.id })

      if (get().activeId !== id) {
        return
      }

      set((current) => ({
        messages: mergeMessages([...older, ...current.messages]),
        hasMoreHistory: older.length >= 30,
        loadingOlder: false,
      }))
    } catch (error) {
      if (get().activeId !== id) {
        return
      }

      set({ loadingOlder: false })
      throw error
    }
  },

  async sendMessage(id, body, replyToId) {
    const temporaryId = -Date.now()
    const replyTo = get().messages.find((message) => message.id === replyToId)
    const temporary: Message = {
      id: temporaryId,
      conversation_id: id,
      sender_id: 0,
      body,
      created_at: new Date().toISOString(),
      read_at: null,
      edited_at: null,
      reply_to_id: replyToId ?? null,
      reply_to: replyTo ? replyPreviewOf(replyTo) : null,
      is_deleted_for_everyone: false,
      is_deleted_for_me: false,
      pending: true,
    }

    set((state) => ({
      sending: true,
      messages:
        state.activeId === id ? [...state.messages, temporary] : state.messages,
    }))

    try {
      const message = await sendMessageRequest(id, body, replyToId)

      set((state) => ({
        sending: false,
        messages:
          state.activeId === id
            ? applyMessageChange(
                state.messages.map((item) =>
                  item.id === temporaryId ? message : item
                ),
                message
              )
            : state.messages,
        ...withPreview(state, id, message),
      }))

      return message
    } catch (error) {
      set((state) => ({
        sending: false,
        messages:
          state.activeId === id
            ? state.messages.filter((item) => item.id !== temporaryId)
            : state.messages,
      }))

      throw error
    }
  },

  async updateMessage(id, messageId, body) {
    const previous = get().messages.find((message) => message.id === messageId)

    if (!previous) {
      throw new Error("Message not found.")
    }

    const optimistic: Message = {
      ...previous,
      body,
      edited_at: new Date().toISOString(),
    }

    set((state) => ({
      messages: applyMessageChange(
        state.messages.map((message) =>
          message.id === messageId ? optimistic : message
        ),
        optimistic
      ),
    }))

    try {
      const message = await updateMessageRequest(id, messageId, body)

      set((state) => ({
        messages: applyMessageChange(
          state.messages.map((item) => (item.id === messageId ? message : item)),
          message
        ),
        ...withPreview(state, id, message, false),
      }))

      return message
    } catch (error) {
      set((state) => ({
        messages: applyMessageChange(
          state.messages.map((message) =>
            message.id === messageId ? previous : message
          ),
          previous
        ),
      }))

      throw error
    }
  },

  async deleteMessage(id, messageId, scope) {
    const messages = get().messages
    const previousIndex = messages.findIndex((message) => message.id === messageId)
    const previous = messages[previousIndex]

    if (!previous) {
      throw new Error("Message not found.")
    }

    const previousConversations = get().conversations
    const previousActiveConversation = get().activeConversation
    const tombstone = tombstoneOf(previous, scope)

    set((state) => {
      const messages =
        scope === "me"
          ? state.messages.filter((message) => message.id !== messageId)
          : applyMessageChange(
              state.messages.map((message) =>
                message.id === messageId ? tombstone : message
              ),
              tombstone
            )

      return {
        messages: applyMessageChange(messages, tombstone),
        ...withPreview(state, id, tombstone, false),
      }
    })

    try {
      const message = await deleteMessageRequest(id, messageId, scope)

      set((state) => {
        const messages =
          scope === "me"
            ? state.messages.filter((item) => item.id !== messageId)
            : applyMessageChange(
                state.messages.map((item) => (item.id === messageId ? message : item)),
                message
              )

        return {
          messages: applyMessageChange(messages, message),
          ...withPreview(
            state,
            id,
            scope === "me" ? tombstone : message,
            false
          ),
        }
      })

      return message
    } catch (error) {
      set((state) => ({
        messages: applyMessageChange(
          restoreMessage(state.messages, previous, previousIndex),
          previous
        ),
        conversations: previousConversations,
        activeConversation: previousActiveConversation,
      }))

      throw error
    }
  },

  async markRead(id) {
    const conversation = get().conversations.find((item) => item.id === id)
    const previousUnread = conversation?.unread_count ?? 0

    try {
      await markReadRequest(id)

      set((state) => ({
        conversations: state.conversations.map((item) =>
          item.id === id ? { ...item, unread_count: 0 } : item
        ),
        unreadCount: Math.max(0, state.unreadCount - previousUnread),
      }))

      if (previousUnread === 0) {
        void get().fetchUnreadCount()
      }
    } catch {
      // Read receipts are best-effort.
    }
  },

  reset() {
    threadRequest++
    set({
      conversations: [],
      conversationsMeta: null,
      conversationsLoading: false,
      conversationsRefreshing: false,
      conversationsError: null,
      unreadCount: 0,
      activeId: null,
      activeConversation: null,
      messages: [],
      messagesLoading: false,
      messagesError: null,
      loadingOlder: false,
      hasMoreHistory: false,
      sending: false,
    })
  },
}))
