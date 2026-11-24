import { create } from "zustand"
import { persist } from "zustand/middleware"

import type { User } from "@/types"

interface AuthState {
  user: User | null
  token: string | null
  isHydrating: boolean
  setAuth: (user: User, token: string) => void
  setToken: (token: string) => void
  setUser: (user: User) => void
  setHydrating: (value: boolean) => void
  clearAuth: () => void
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      user: null,
      token: null,
      isHydrating: true,
      setAuth: (user, token) => set({ user, token, isHydrating: false }),
      setToken: (token) => set({ token }),
      setUser: (user) => set({ user }),
      setHydrating: (isHydrating) => set({ isHydrating }),
      clearAuth: () => set({ user: null, token: null, isHydrating: false }),
    }),
    {
      name: "taskmate-auth",
      partialize: (state) => ({ user: state.user, token: state.token }),
    }
  )
)
