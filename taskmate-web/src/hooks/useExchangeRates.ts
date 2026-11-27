import { useCallback, useContext, useEffect, useMemo } from "react"

import {
  ExchangeRatesContext,
  type ExchangeRateStatus,
} from "@/hooks/exchangeRatesContext"
import { FALLBACK_CURRENCY } from "@/lib/currencies"

/**
 * Request USD-based rates for the given currencies and expose their status.
 * Rates are cached globally, so multiple cards share one request per currency.
 */
export function useExchangeRates(currencies: string[]) {
  const context = useContext(ExchangeRatesContext)

  if (!context) {
    throw new Error("useExchangeRates must be used within ExchangeRatesProvider")
  }

  const { rates, asOf, status: contextStatus, ensureRates, refreshRates } = context
  const key = [...new Set(currencies)].sort().join(",")
  const requestedCurrencies = useMemo(
    () => (key === "" ? [] : key.split(",")),
    [key]
  )

  useEffect(() => {
    ensureRates(requestedCurrencies)
  }, [ensureRates, requestedCurrencies])

  const missing = requestedCurrencies.filter(
    (currency) =>
      currency !== FALLBACK_CURRENCY && rates[currency] === undefined
  )

  const status: ExchangeRateStatus =
    missing.length === 0
      ? "ready"
      : contextStatus === "fallback"
        ? "fallback"
        : "loading"

  const refresh = useCallback(
    () => refreshRates(requestedCurrencies),
    [refreshRates, requestedCurrencies]
  )

  return {
    rates,
    asOf,
    status,
    missing,
    refresh,
  }
}
