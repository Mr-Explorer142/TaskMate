import { useEffect, useState } from "react"

import { FALLBACK_CURRENCY } from "@/lib/currencies"

const API_URL = "https://api.frankfurter.dev/v2/rates"
const CACHE_TTL_MS = 60 * 60 * 1000

interface FrankfurterRate {
  base: string
  quote: string
  rate: number
}

interface CachedRates {
  rates: Record<string, number>
  expiresAt: number
}

const cache = new Map<string, CachedRates>()
const inFlight = new Map<string, Promise<Record<string, number>>>()

async function loadRates(quotes: string[]): Promise<Record<string, number>> {
  const uniqueQuotes = [...new Set(quotes)]
    .filter((quote) => quote !== FALLBACK_CURRENCY)
    .sort()

  if (uniqueQuotes.length === 0) {
    return { [FALLBACK_CURRENCY]: 1 }
  }

  const cacheKey = uniqueQuotes.join(",")
  const cached = cache.get(cacheKey)

  if (cached && cached.expiresAt > Date.now()) {
    return cached.rates
  }

  const pending = inFlight.get(cacheKey)

  if (pending) {
    return pending
  }

  const request = (async () => {
    const response = await fetch(
      `${API_URL}?base=${FALLBACK_CURRENCY}&quotes=${uniqueQuotes.join(",")}`
    )

    if (!response.ok) {
      throw new Error(`Frankfurter returned ${response.status}`)
    }

    const entries = (await response.json()) as FrankfurterRate[]
    const rates: Record<string, number> = { [FALLBACK_CURRENCY]: 1 }

    for (const entry of entries) {
      if (typeof entry.rate === "number" && entry.rate > 0) {
        rates[entry.quote] = entry.rate
      }
    }

    cache.set(cacheKey, {
      rates,
      expiresAt: Date.now() + CACHE_TTL_MS,
    })

    return rates
  })().finally(() => {
    inFlight.delete(cacheKey)
  })

  inFlight.set(cacheKey, request)

  return request
}

/**
 * Fetch USD-based rates for the given currencies, shared across all callers.
 * Falls back to USD whenever a rate is unavailable.
 */
export function useExchangeRates(currencies: string[]): Record<string, number> {
  const key = [...new Set(currencies)].sort().join(",")
  const [rates, setRates] = useState<Record<string, number>>({
    [FALLBACK_CURRENCY]: 1,
  })

  useEffect(() => {
    let active = true

    void loadRates(key === "" ? [] : key.split(","))
      .then((nextRates) => {
        if (active) {
          setRates(nextRates)
        }
      })
      .catch(() => {
        if (active) {
          setRates({ [FALLBACK_CURRENCY]: 1 })
        }
      })

    return () => {
      active = false
    }
  }, [key])

  return rates
}
