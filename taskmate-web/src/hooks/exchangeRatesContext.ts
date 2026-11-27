import { createContext } from "react"

export type ExchangeRateStatus = "idle" | "loading" | "ready" | "fallback"

export interface ExchangeRatesState {
  rates: Record<string, number>
  asOf: string | null
  status: ExchangeRateStatus
}

export interface ExchangeRatesContextValue extends ExchangeRatesState {
  ensureRates: (currencies: string[]) => void
  refreshRates: (currencies?: string[]) => Promise<void>
}

export const ExchangeRatesContext =
  createContext<ExchangeRatesContextValue | null>(null)
