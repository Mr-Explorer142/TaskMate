import { create } from "zustand"

import { getApiErrorMessage } from "@/api/client"
import {
  getMessages,
  listConversations,
  markRead as markReadRequest,
  sendMessage as sendMessageRequest,
  unreadCount as unreadCountRequest,
} from "@/api/messages"
import type { Conversation, Message, PaginationMeta } from "@/types"

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
  sendMessage: (id: number, body: string) => Promise<Message>
  markRead: (id: number) => Promise<void>
  reset: () => void
}

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

function previewOf(body: string): string {
  return body.length > 120 ? `${body.slice(0, 117)}...` : body
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

      set((state) => ({
        conversations: moveConversationToTop(
          state.conversations,
          id,
          (conversation) => ({
            ...conversation,
            last_message: {
              id: latest.id,
              sender_id: latest.sender_id,
              body: previewOf(latest.body),
              created_at: latest.created_at,
            },
            updated_at: latest.created_at,
          })
        ),
      }))

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

  async sendMessage(id, body) {
    const temporaryId = -Date.now()
    const temporary: Message = {
      id: temporaryId,
      conversation_id: id,
      sender_id: 0,
      body,
      created_at: new Date().toISOString(),
      read_at: null,
      pending: true,
    }

    set((state) => ({
      sending: true,
      messages:
        state.activeId === id ? [...state.messages, temporary] : state.messages,
    }))

    try {
      const message = await sendMessageRequest(id, body)

      set((state) => ({
        sending: false,
        messages:
          state.activeId === id
            ? mergeMessages(
                state.messages.map((item) =>
                  item.id === temporaryId ? message : item
                )
              )
            : state.messages,
        conversations: moveConversationToTop(
          state.conversations,
          id,
          (conversation) => ({
            ...conversation,
            last_message: {
              id: message.id,
              sender_id: message.sender_id,
              body: previewOf(message.body),
              created_at: message.created_at,
            },
            updated_at: message.created_at,
          })
        ),
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
