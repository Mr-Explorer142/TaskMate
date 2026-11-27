import { currencyForCountry, FALLBACK_CURRENCY } from "@/lib/currencies"
import { useAuthStore } from "@/stores/useAuthStore"

/**
 * Resolve the signed-in viewer's currency. Guests and unsupported countries
 * always use the canonical USD currency.
 */
export function useViewerCurrency() {
  const isHydrating = useAuthStore((state) => state.isHydrating)
  const isGuest = !useAuthStore((state) => state.token)
  const country = useAuthStore((state) => state.user?.country ?? null)
  const matchedCurrency = currencyForCountry(country)

  const currency =
    isGuest || isHydrating || matchedCurrency === null
      ? FALLBACK_CURRENCY
      : matchedCurrency

  return { country, currency, isGuest, isHydrating }
}
