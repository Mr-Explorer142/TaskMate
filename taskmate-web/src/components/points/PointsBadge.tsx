import { useEffect } from "react"
import { Coins } from "lucide-react"
import { Link } from "react-router-dom"

import { Button } from "@/components/ui/button"
import { useAuthStore } from "@/stores/useAuthStore"
import { usePointsStore } from "@/stores/usePointsStore"

export function PointsBadge() {
  const summary = usePointsStore((state) => state.summary)
  const fetchSummary = usePointsStore((state) => state.fetchSummary)
  const authBalance = useAuthStore((state) => state.user?.points_balance)

  useEffect(() => {
    void fetchSummary()
  }, [fetchSummary])

  const balance = summary?.balance ?? authBalance ?? 0

  return (
    <Button variant="outline" size="sm" asChild className="gap-1.5">
      <Link to="/points" aria-label="Points balance">
        <Coins className="size-4 text-primary" />
        <span className="tabular-nums">{balance.toLocaleString("en-US")}</span>
        <span className="hidden sm:inline">pts</span>
      </Link>
    </Button>
  )
}
