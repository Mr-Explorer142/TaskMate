import {
  format,
  formatDistanceToNowStrict,
  isSameDay,
  isToday,
  isYesterday,
  parseISO,
} from "date-fns"

import type { Message, TaskType } from "@/types"

export const CURRENCY_SYMBOL = "$"

export function formatPoints(value: string | number): string {
  return Math.round(Number(value)).toLocaleString("en-US")
}

export function formatCurrency(value: string | number): string {
  return `${CURRENCY_SYMBOL}${Number(value).toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`
}

export function formatReward(type: TaskType, reward: string | number): string {
  if (type === "points") {
    return `${formatPoints(reward)} pts`
  }

  return formatCurrency(reward)
}

export function formatDate(value: string | null | undefined): string {
  if (!value) {
    return ""
  }

  return format(parseISO(value), "MMM d, yyyy")
}

export function formatDeadline(deadline: string | null): string {
  if (!deadline) {
    return "No deadline"
  }

  return `Due ${formatDate(deadline)}`
}

export function formatPostedDate(createdAt: string): string {
  return formatDate(createdAt)
}

export function formatMonthYear(value: string | null | undefined): string {
  if (!value) {
    return ""
  }

  return format(parseISO(value), "MMMM yyyy")
}

export function formatDateTime(value: string | null | undefined): string {
  if (!value) {
    return ""
  }

  return format(parseISO(value), "MMM d, yyyy 'at' h:mm a")
}

export function formatTime(value: string | null | undefined): string {
  if (!value) {
    return ""
  }

  return format(parseISO(value), "h:mm a")
}

export function formatRelativeDate(value: string | null | undefined): string {
  if (!value) {
    return ""
  }

  return formatDistanceToNowStrict(parseISO(value), { addSuffix: true })
}

export function formatDayLabel(value: string | null | undefined): string {
  if (!value) {
    return ""
  }

  const date = parseISO(value)

  if (isToday(date)) {
    return "Today"
  }

  if (isYesterday(date)) {
    return "Yesterday"
  }

  return format(date, "MMMM d, yyyy")
}

export function isNewMessageDay(
  message: Message,
  previous: Message | undefined
): boolean {
  if (!previous || !message.created_at || !previous.created_at) {
    return Boolean(message.created_at)
  }

  return !isSameDay(parseISO(message.created_at), parseISO(previous.created_at))
}

export function todayInputValue(): string {
  return format(new Date(), "yyyy-MM-dd")
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
