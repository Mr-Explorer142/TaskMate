import { useEffect } from "react"
import { useLocation } from "react-router-dom"

const NAVBAR_OFFSET = 80

export function ScrollToTop() {
  const { pathname, search, hash, key } = useLocation()

  useEffect(() => {
    if (!hash) {
      window.scrollTo({ top: 0, left: 0, behavior: "auto" })
      return
    }

    const id = decodeURIComponent(hash.slice(1))
    let attempts = 0
    let cancelled = false
    let timeout: number | undefined

    function scrollToElement() {
      if (cancelled) {
        return
      }

      const element = document.getElementById(id)

      if (!element) {
        attempts += 1

        if (attempts <= 20) {
          timeout = window.setTimeout(scrollToElement, 50)
        }

        return
      }

      const top =
        element.getBoundingClientRect().top + window.scrollY - NAVBAR_OFFSET

      window.scrollTo({ top: Math.max(0, top), behavior: "smooth" })
    }

    const frame = window.requestAnimationFrame(scrollToElement)

    return () => {
      cancelled = true
      window.cancelAnimationFrame(frame)

      if (timeout !== undefined) {
        window.clearTimeout(timeout)
      }
    }
  }, [pathname, search, hash, key])

  return null
}
