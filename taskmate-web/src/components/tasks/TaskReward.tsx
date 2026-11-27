import { ArrowLeftRight, Loader2, RefreshCw } from "lucide-react"
import { useState } from "react"

import { Button } from "@/components/ui/button"
import { useExchangeRates } from "@/hooks/useExchangeRates"
import { useViewerCurrency } from "@/hooks/useViewerCurrency"
import {
  convertUsdToCurrency,
  currencyForCountry,
  FALLBACK_CURRENCY,
  formatMoney,
  hasUsdRate,
} from "@/lib/currencies"
import { formatDate, formatReward } from "@/lib/format"
import type { TaskType } from "@/types"

export type TaskRewardVariant = "compact" | "detail" | "inline"

export function TaskReward({
  type,
  reward,
  uploaderCountry,
  variant = "compact",
}: {
  type: TaskType
  reward: string | number
  uploaderCountry?: string | null
  variant?: TaskRewardVariant
}) {
  if (type === "points") {
    return <span>{formatReward(type, reward)}</span>
  }

  return (
    <PaidTaskReward
      reward={reward}
      uploaderCountry={uploaderCountry}
      variant={variant}
    />
  )
}

function PaidTaskReward({
  reward,
  uploaderCountry,
  variant,
}: {
  reward: string | number
  uploaderCountry?: string | null
  variant: TaskRewardVariant
}) {
  const [showViewerCurrency, setShowViewerCurrency] = useState(true)
  const { currency: viewerCurrency, isGuest, isHydrating } = useViewerCurrency()
  const uploaderCurrency =
    currencyForCountry(uploaderCountry) ?? FALLBACK_CURRENCY
  const shouldRequestUploaderRate =
    !isGuest && !isHydrating && uploaderCurrency !== viewerCurrency
  const requestedCurrencies = shouldRequestUploaderRate
    ? [viewerCurrency, uploaderCurrency]
    : [viewerCurrency]
  const { rates, asOf, status, refresh } = useExchangeRates(requestedCurrencies)

  const viewerRateReady = hasUsdRate(viewerCurrency, rates)
  const uploaderRateReady =
    !shouldRequestUploaderRate || hasUsdRate(uploaderCurrency, rates)
  const canToggle =
    !isHydrating &&
    !isGuest &&
    viewerCurrency !== uploaderCurrency &&
    viewerRateReady &&
    uploaderRateReady
  const activeCurrency =
    canToggle && !showViewerCurrency ? uploaderCurrency : viewerCurrency
  const alternateCurrency =
    activeCurrency === viewerCurrency ? uploaderCurrency : viewerCurrency
  const convertedAmount = convertUsdToCurrency(reward, activeCurrency, rates)
  const displayCurrency =
    convertedAmount === null ? FALLBACK_CURRENCY : activeCurrency
  const displayAmount = convertedAmount ?? Number(reward)
  const isFallback =
    convertedAmount === null && activeCurrency !== FALLBACK_CURRENCY
  const usesLiveRate = activeCurrency !== FALLBACK_CURRENCY
  const rateDate = asOf ? formatDate(asOf) : null
  const rateUnavailable =
    usesLiveRate && (isFallback || status === "fallback")
  const rateLoading = usesLiveRate && status === "loading"
  const rateLabel = !usesLiveRate
    ? "Canonical USD amount"
    : rateUnavailable
      ? "Rates unavailable · showing USD"
      : rateLoading
        ? "Updating rates…"
        : rateDate
          ? `Rate as of ${rateDate}`
          : "Rates loading…"
  const shortRateLabel = rateUnavailable
    ? "Rate unavailable"
    : rateLoading
      ? "Updating rate…"
      : "Live rate"
  const showRateFeedback =
    variant === "detail" || (usesLiveRate && (rateUnavailable || rateLoading))
  const showRefresh = usesLiveRate && (variant === "detail" || rateUnavailable)

  return (
    <span className="inline-flex flex-wrap items-center gap-x-2 gap-y-1">
      <span
        className="tabular-nums"
        title={
          displayCurrency === FALLBACK_CURRENCY
            ? "Canonical USD amount"
            : `Canonical ${formatMoney(reward, FALLBACK_CURRENCY)} · price in ${displayCurrency}${rateDate ? `, rate as of ${rateDate}` : ""}`
        }
      >
        {formatMoney(displayAmount, displayCurrency)}
        {isFallback ? (
          <span className="ml-1 text-xs font-normal text-muted-foreground">
            (USD)
          </span>
        ) : null}
      </span>

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

      {showRateFeedback ? (
        <span
          className="inline-flex items-center gap-1 text-xs font-normal text-muted-foreground"
          title={rateLabel}
        >
          {rateLoading ? (
            <Loader2 className="size-3 animate-spin" aria-hidden="true" />
          ) : null}
          <span className={variant === "detail" ? undefined : "text-[10px]"}>
            {variant === "detail" ? rateLabel : shortRateLabel}
          </span>
          {showRefresh ? (
            <Button
              type="button"
              variant="ghost"
              size="icon-xs"
              className="size-5 text-muted-foreground hover:text-primary"
              title="Refresh exchange rates"
              aria-label="Refresh exchange rates"
              disabled={rateLoading}
              onClick={() => void refresh()}
            >
              <RefreshCw aria-hidden="true" />
            </Button>
          ) : null}
        </span>
      ) : null}
    </span>
  )
}
