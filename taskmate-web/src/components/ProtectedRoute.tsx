import type { ReactNode } from "react"
import { Navigate, Outlet, useLocation } from "react-router-dom"

import { Skeleton } from "@/components/ui/skeleton"
import { useAuthStore } from "@/stores/useAuthStore"

export function PageLoadingSkeleton() {
  return (
    <div className="mx-auto w-full max-w-3xl space-y-4 py-4">
      <Skeleton className="h-8 w-48" />
      <Skeleton className="h-4 w-72" />
      <Skeleton className="h-48 w-full rounded-lg" />
      <Skeleton className="h-32 w-full rounded-lg" />
    </div>
  )
}

export function ProtectedRoute() {
  const token = useAuthStore((state) => state.token)
  const isHydrating = useAuthStore((state) => state.isHydrating)
  const location = useLocation()

  if (isHydrating) {
    return <PageLoadingSkeleton />
  }

  if (!token) {
    return (
      <Navigate
        to="/login"
        replace
        state={{ from: `${location.pathname}${location.search}` }}
      />
    )
  }

  return <Outlet />
}

export function PublicOnlyRoute({ children }: { children: ReactNode }) {
  const token = useAuthStore((state) => state.token)
  const isHydrating = useAuthStore((state) => state.isHydrating)

  if (isHydrating) {
    return <PageLoadingSkeleton />
  }

  if (token) {
    return <Navigate to="/" replace />
  }

  return <>{children}</>
}
