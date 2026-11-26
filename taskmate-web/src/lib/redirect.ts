/**
 * Only allow internal paths so the redirect parameter cannot be used for an
 * open redirect.
 */
export function safeRedirect(
  value: string | null | undefined
): string | null {
  if (!value) {
    return null
  }

  if (!value.startsWith("/") || value.startsWith("//")) {
    return null
  }

  return value
}

export function loginUrl(redirect?: string | null): string {
  const target = safeRedirect(redirect)

  return target ? `/login?redirect=${encodeURIComponent(target)}` : "/login"
}

export function registerUrl(redirect?: string | null): string {
  const target = safeRedirect(redirect)

  return target ? `/register?redirect=${encodeURIComponent(target)}` : "/register"
}
