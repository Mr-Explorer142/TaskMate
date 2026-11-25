import { format, parseISO } from "date-fns"

import type { TaskType } from "@/types"

export const CURRENCY_SYMBOL = "$"

export function formatReward(type: TaskType, reward: string | number): string {
  const value = Number(reward)

  if (type === "points") {
    return `${Math.round(value).toLocaleString("en-US")} pts`
  }

  return `${CURRENCY_SYMBOL}${value.toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`
}

export function formatDeadline(deadline: string | null): string {
  if (!deadline) {
    return "No deadline"
  }

  return `Due ${format(parseISO(deadline), "MMM d, yyyy")}`
}

export function formatPostedDate(createdAt: string): string {
  return format(parseISO(createdAt), "MMM d, yyyy")
}

export function formatRatingLabel(
  average: number | null | undefined,
  count?: number
): string {
  if (average == null) {
    return "New"
  }

  if (count === undefined) {
    return Number(average).toFixed(1)
  }

  if (count <= 0) {
    return "New"
  }

  return `${Number(average).toFixed(1)} (${count})`
}
