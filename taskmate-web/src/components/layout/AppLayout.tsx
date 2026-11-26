import { Suspense, useEffect } from "react"
import { Outlet, useLocation } from "react-router-dom"

import { Footer } from "@/components/layout/Footer"
import { Navbar } from "@/components/layout/Navbar"
import { PageLoadingSkeleton } from "@/components/ProtectedRoute"
import { VerifyEmailBanner } from "@/components/VerifyEmailBanner"
import { usePolling } from "@/hooks/usePolling"
import { useAuthStore } from "@/stores/useAuthStore"
import { useChatStore } from "@/stores/useChatStore"

export function AppLayout() {
  const token = useAuthStore((state) => state.token)
  const fetchUnreadCount = useChatStore((state) => state.fetchUnreadCount)
  const location = useLocation()
  const isHome = location.pathname === "/"

  useEffect(() => {
    if (token) {
      void fetchUnreadCount()
    }
  }, [token, fetchUnreadCount])

  usePolling(() => fetchUnreadCount(), 15_000, Boolean(token))

  return (
    <div className="flex min-h-screen flex-col bg-background">
      <Navbar />

      <VerifyEmailBanner />

      <main
        className={
          isHome
            ? "flex-1"
            : "mx-auto w-full max-w-5xl flex-1 px-4 py-8 sm:py-10"
        }
      >
        <Suspense fallback={<PageLoadingSkeleton />}>
          <Outlet />
        </Suspense>
      </main>

      <Footer />
    </div>
  )
}
