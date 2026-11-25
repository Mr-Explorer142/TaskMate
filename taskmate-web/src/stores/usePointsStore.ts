import { create } from "zustand"

import { getApiErrorMessage } from "@/api/client"
import {
  summary as summaryRequest,
  transactions as transactionsRequest,
} from "@/api/points"
import { useAuthStore } from "@/stores/useAuthStore"
import type { PaginationMeta, PointTransaction, PointsSummary } from "@/types"

interface PointsState {
  summary: PointsSummary | null
  summaryLoading: boolean
  summaryError: string | null
  transactions: PointTransaction[]
  meta: PaginationMeta | null
  transactionsLoading: boolean
  transactionsError: string | null
  fetchSummary: () => Promise<void>
  fetchTransactions: (page?: number) => Promise<void>
  refresh: () => Promise<void>
  reset: () => void
}

function syncBalance(summary: PointsSummary) {
  const { user, setUser } = useAuthStore.getState()

  if (user && user.points_balance !== summary.balance) {
    setUser({ ...user, points_balance: summary.balance })
  }
}

export const usePointsStore = create<PointsState>((set, get) => ({
  summary: null,
  summaryLoading: false,
  summaryError: null,
  transactions: [],
  meta: null,
  transactionsLoading: false,
  transactionsError: null,

  async fetchSummary() {
    set({ summaryLoading: true, summaryError: null })

    try {
      const summary = await summaryRequest()
      syncBalance(summary)
      set({ summary, summaryLoading: false, summaryError: null })
    } catch (error) {
      set({
        summaryLoading: false,
        summaryError: getApiErrorMessage(error),
      })
    }
  },

  async fetchTransactions(page = 1) {
    set({ transactionsLoading: true, transactionsError: null })

    try {
      const result = await transactionsRequest(page)

      set({
        transactions: result.data,
        meta: result.meta,
        transactionsLoading: false,
        transactionsError: null,
      })
    } catch (error) {
      set({
        transactionsLoading: false,
        transactionsError: getApiErrorMessage(error),
      })
    }
  },

  async refresh() {
    await Promise.all([get().fetchSummary(), get().fetchTransactions(get().meta?.current_page ?? 1)])
  },

  reset() {
    set({
      summary: null,
      summaryLoading: false,
      summaryError: null,
      transactions: [],
      meta: null,
      transactionsLoading: false,
      transactionsError: null,
    })
  },
}))
