import { SOCIAL_REDIRECT_STORAGE_KEY } from "@/lib/constants"

/**
 * Only allow internal paths so the redirect parameter cannot be used for an
 * open redirect. A fixed origin is used to normalize paths such as `/\evil.com`,
 * which some browsers would otherwise treat as protocol-relative.
 */
export function safeRedirect(
  value: string | null | undefined
): string | null {
  if (!value || !value.startsWith("/")) {
    return null
  }

  try {
    const origin = "http://taskmate.internal"
    const parsed = new URL(value, origin)

    if (parsed.origin !== origin) {
      return null
    }

    return `${parsed.pathname}${parsed.search}${parsed.hash}`
  } catch {
    return null
  }
}

export function loginUrl(redirect?: string | null): string {
  const target = safeRedirect(redirect)

  return target ? `/login?redirect=${encodeURIComponent(target)}` : "/login"
}

export function registerUrl(redirect?: string | null): string {
  const target = safeRedirect(redirect)

  return target ? `/register?redirect=${encodeURIComponent(target)}` : "/register"
}

export function storeSocialRedirect(value: string | null | undefined): void {
  try {
    const target = safeRedirect(value)

    if (target) {
      window.sessionStorage.setItem(SOCIAL_REDIRECT_STORAGE_KEY, target)
    } else {
      window.sessionStorage.removeItem(SOCIAL_REDIRECT_STORAGE_KEY)
    }
  } catch {
    // Storage can be unavailable in private browsing; the login still works.
  }
}

export function takeSocialRedirect(): string | null {
  try {
    const stored = window.sessionStorage.getItem(SOCIAL_REDIRECT_STORAGE_KEY)
    window.sessionStorage.removeItem(SOCIAL_REDIRECT_STORAGE_KEY)

    return safeRedirect(stored)
  } catch {
    return null
  }
}
