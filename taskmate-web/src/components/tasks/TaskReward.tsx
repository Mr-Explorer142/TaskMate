import { ArrowLeftRight } from "lucide-react"
import { useState } from "react"

import { Button } from "@/components/ui/button"
import { useExchangeRates } from "@/hooks/useExchangeRates"
import {
  currencyForCountry,
  FALLBACK_CURRENCY,
  formatMoney,
} from "@/lib/currencies"
import { formatReward } from "@/lib/format"
import { useAuthStore } from "@/stores/useAuthStore"
import type { TaskType } from "@/types"

export function TaskReward({
  type,
  reward,
  uploaderCountry,
}: {
  type: TaskType
  reward: string | number
  uploaderCountry?: string | null
}) {
  if (type === "points") {
    return <span>{formatReward(type, reward)}</span>
  }

  return (
    <PaidTaskReward reward={reward} uploaderCountry={uploaderCountry} />
  )
}

function PaidTaskReward({
  reward,
  uploaderCountry,
}: {
  reward: string | number
  uploaderCountry?: string | null
}) {
  const [showViewerCurrency, setShowViewerCurrency] = useState(true)
  const isHydrating = useAuthStore((state) => state.isHydrating)
  const isGuest = !useAuthStore((state) => state.token)
  const viewerCountry = useAuthStore((state) => state.user?.country ?? null)

  const viewerCurrency = currencyForCountry(viewerCountry) ?? FALLBACK_CURRENCY
  const uploaderCurrency =
    currencyForCountry(uploaderCountry) ?? FALLBACK_CURRENCY
  const rates = useExchangeRates([viewerCurrency, uploaderCurrency])

  const canToggle = !isHydrating && !isGuest && viewerCurrency !== uploaderCurrency
  const activeCurrency =
    canToggle && !showViewerCurrency ? uploaderCurrency : viewerCurrency
  const alternateCurrency =
    activeCurrency === viewerCurrency ? uploaderCurrency : viewerCurrency
  const convertedAmount = Number(reward) * (rates[activeCurrency] ?? 1)

  return (
    <span className="inline-flex items-center gap-1">
      <span>{formatMoney(convertedAmount, activeCurrency)}</span>
      {canToggle ? (
        <Button
          type="button"
          variant="ghost"
          size="icon-xs"
          className="text-muted-foreground hover:text-primary"
          title={`Show price in ${alternateCurrency}`}
          aria-label={`Show price in ${alternateCurrency}`}
          onClick={() => setShowViewerCurrency((current) => !current)}
        >
          <ArrowLeftRight aria-hidden="true" />
        </Button>
      ) : null}
    </span>
  )
}
