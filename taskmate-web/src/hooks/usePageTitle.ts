import { useEffect } from "react"

const SITE_NAME = "TaskMate"

export function usePageTitle(title: string): void {
  useEffect(() => {
    document.title = `${title} | ${SITE_NAME}`
  }, [title])
}
