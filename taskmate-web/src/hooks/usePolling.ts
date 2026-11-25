import { useEffect, useRef } from "react"

/**
 * Run a callback on an interval while the tab is visible.
 *
 * - Pauses when `document.visibilityState` is `hidden` and resumes when visible.
 * - Never overlaps requests: a tick is skipped while the previous one is running.
 * - Cleans up the interval and visibility listener on unmount.
 * - Swallows rejected background requests so polling cannot crash a page.
 */
export function usePolling(
  callback: () => void | Promise<void>,
  intervalMs: number,
  enabled = true
) {
  const savedCallback = useRef(callback)
  const inFlight = useRef(false)
  const timer = useRef<number | null>(null)

  useEffect(() => {
    savedCallback.current = callback
  }, [callback])

  useEffect(() => {
    if (!enabled) {
      return
    }

    async function tick() {
      if (document.visibilityState === "hidden" || inFlight.current) {
        return
      }

      inFlight.current = true

      try {
        await savedCallback.current()
      } catch {
        // Background refresh failures are silent; actions surface their own errors.
      } finally {
        inFlight.current = false
      }
    }

    function start() {
      if (timer.current !== null) {
        return
      }

      timer.current = window.setInterval(() => {
        void tick()
      }, intervalMs)
    }

    function stop() {
      if (timer.current === null) {
        return
      }

      window.clearInterval(timer.current)
      timer.current = null
    }

    function handleVisibilityChange() {
      if (document.visibilityState === "hidden") {
        stop()
      } else {
        void tick()
        start()
      }
    }

    if (document.visibilityState !== "hidden") {
      start()
    }

    document.addEventListener("visibilitychange", handleVisibilityChange)

    return () => {
      stop()
      document.removeEventListener("visibilitychange", handleVisibilityChange)
    }
  }, [intervalMs, enabled])
}
