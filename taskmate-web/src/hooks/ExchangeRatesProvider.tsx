import { useCallback, useMemo, useRef, useState } from "react"
import type { ReactNode } from "react"

import {
  ExchangeRatesContext,
  type ExchangeRatesContextValue,
  type ExchangeRatesState,
} from "@/hooks/exchangeRatesContext"
import { FALLBACK_CURRENCY } from "@/lib/currencies"

const API_URL = "https://api.frankfurter.dev/v2/rates"
const CACHE_TTL_MS = 60 * 60 * 1000

interface FrankfurterRate {
  date: string
  base: string
  quote: string
  rate: number
}

const initialState: ExchangeRatesState = {
  rates: { [FALLBACK_CURRENCY]: 1 },
  asOf: null,
  status: "idle",
}

export function ExchangeRatesProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<ExchangeRatesState>(initialState)
  const loadedUntilRef = useRef(new Map<string, number>())
  const requestedRef = useRef(new Set<string>([FALLBACK_CURRENCY]))
  const inFlightRef = useRef(new Map<string, Promise<void>>())

  const fetchRates = useCallback(async (quotes: string[]) => {
    const missing = quotes.filter(
      (quote) => quote !== FALLBACK_CURRENCY && !inFlightRef.current.has(quote)
    )

    if (missing.length === 0) {
      return
    }

    setState((current) => ({ ...current, status: "loading" }))

    const request = (async () => {
      try {
        const response = await fetch(
          `${API_URL}?base=${FALLBACK_CURRENCY}&quotes=${missing.join(",")}`
        )

        if (!response.ok) {
          throw new Error(`Frankfurter returned ${response.status}`)
        }

        const entries = (await response.json()) as FrankfurterRate[]
        const validEntries = entries.filter(
          (entry) =>
            typeof entry.rate === "number" &&
            entry.rate > 0 &&
            typeof entry.quote === "string"
        )
        const returned = new Set(validEntries.map((entry) => entry.quote))
        const allQuotesReturned = missing.every((quote) => returned.has(quote))

        setState((current) => {
          const rates = { ...current.rates }

          for (const entry of validEntries) {
            rates[entry.quote] = entry.rate
          }

          return {
            rates,
            asOf: validEntries[0]?.date ?? current.asOf,
            status:
              validEntries.length > 0 && allQuotesReturned ? "ready" : "fallback",
          }
        })

        const expiresAt = Date.now() + CACHE_TTL_MS

        for (const quote of validEntries.map((entry) => entry.quote)) {
          loadedUntilRef.current.set(quote, expiresAt)
        }

        for (const quote of missing) {
          if (!returned.has(quote)) {
            loadedUntilRef.current.set(quote, 0)
          }
        }
      } catch {
        setState((current) => ({ ...current, status: "fallback" }))

        for (const quote of missing) {
          loadedUntilRef.current.set(quote, 0)
        }
      }
    })()

    for (const quote of missing) {
      inFlightRef.current.set(quote, request)
    }

    void request.finally(() => {
      for (const quote of missing) {
        if (inFlightRef.current.get(quote) === request) {
          inFlightRef.current.delete(quote)
        }
      }
    })
  }, [])

  const ensureRates = useCallback(
    (currencies: string[]) => {
      const uniqueCurrencies = [...new Set(currencies)]

      for (const currency of uniqueCurrencies) {
        requestedRef.current.add(currency)
      }

      const stale = uniqueCurrencies.filter(
        (currency) =>
          currency !== FALLBACK_CURRENCY &&
          !inFlightRef.current.has(currency) &&
          (loadedUntilRef.current.get(currency) ?? 0) <= Date.now()
      )

      if (stale.length > 0) {
        void fetchRates(stale)
      }
    },
    [fetchRates]
  )

  const refreshRates = useCallback(
    async (currencies?: string[]) => {
      const targets = [
        ...new Set(currencies?.length ? currencies : [...requestedRef.current]),
      ].filter((currency) => currency !== FALLBACK_CURRENCY)

      for (const currency of targets) {
        requestedRef.current.add(currency)
        loadedUntilRef.current.set(currency, 0)
      }

      await fetchRates(
        targets.filter((currency) => !inFlightRef.current.has(currency))
      )
    },
    [fetchRates]
  )

  const value = useMemo<ExchangeRatesContextValue>(
    () => ({ ...state, ensureRates, refreshRates }),
    [state, ensureRates, refreshRates]
  )

  return (
    <ExchangeRatesContext.Provider value={value}>
      {children}
    </ExchangeRatesContext.Provider>
  )
}
