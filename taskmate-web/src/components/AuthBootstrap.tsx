import { useEffect, useRef, type ReactNode } from "react"

import { me } from "@/api/auth"
import { useAuthStore } from "@/stores/useAuthStore"

export function AuthBootstrap({ children }: { children: ReactNode }) {
  const started = useRef(false)

  useEffect(() => {
    if (started.current) {
      return
    }

    started.current = true

    const { token, setUser, setHydrating, clearAuth } = useAuthStore.getState()

    if (!token) {
      setHydrating(false)
      return
    }

    setHydrating(true)

    // Intentionally not cancelled on cleanup: in StrictMode the first effect is
    // discarded, and we still need this promise to settle the hydrating state.
    void me()
      .then((user) => setUser(user))
      .catch(() => clearAuth())
      .finally(() => setHydrating(false))
  }, [])

  return <>{children}</>
}
