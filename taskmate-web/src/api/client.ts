import axios from "axios"
import type { AxiosError } from "axios"
import { toast } from "sonner"

import { useAuthStore } from "@/stores/useAuthStore"
import type { ApiErrorBody } from "@/types"

export const API_BASE_URL = import.meta.env.VITE_API_URL ?? "http://localhost:8000/api/v1"

export const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    Accept: "application/json",
  },
})

api.interceptors.request.use((config) => {
  const token = useAuthStore.getState().token

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

const lastNoticeAt = new Map<string, number>()

function notifyOnce(key: string, message: string, description: string) {
  const now = Date.now()

  if (now - (lastNoticeAt.get(key) ?? 0) < 5_000) {
    return
  }

  lastNoticeAt.set(key, now)
  toast.error(message, { description })
}

function isPublicAuthRequest(url: string): boolean {
  return [
    "/auth/login",
    "/auth/register",
    "/auth/me",
    "/auth/verify",
    "/auth/resend",
    "/auth/google",
    "/auth/github",
    "/password/forgot",
    "/password/reset",
    "/forgot-password",
    "/reset-password",
  ].some((path) => url.includes(path))
}

api.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiErrorBody>) => {
    const status = error.response?.status
    const url = error.config?.url ?? ""

    if (status === 401 && !isPublicAuthRequest(url)) {
      useAuthStore.getState().clearAuth()
    }

    if (status === 403) {
      notifyOnce(
        "forbidden",
        "You don't have permission to do that.",
        "Check with the task owner or try a different action."
      )
    }

    if (status === 429) {
      notifyOnce(
        "rate-limited",
        "Too many attempts.",
        "Please wait a moment, then try again."
      )
    }

    return Promise.reject(error)
  }
)

export function getApiErrorMessage(error: unknown): string {
  if (axios.isAxiosError<ApiErrorBody>(error)) {
    const errors = error.response?.data?.errors

    if (errors) {
      const firstError = Object.values(errors).flat().find(Boolean)

      if (firstError) {
        return firstError
      }
    }

    return error.response?.data?.message ?? error.message
  }

  if (error instanceof Error) {
    return error.message
  }

  return "Something went wrong. Please try again."
}

export function getFieldErrors(error: unknown): Record<string, string> {
  if (!axios.isAxiosError<ApiErrorBody>(error)) {
    return {}
  }

  const errors = error.response?.data?.errors

  if (!errors) {
    return {}
  }

  return Object.fromEntries(
    Object.entries(errors).map(([field, messages]) => [field, messages[0] ?? ""])
  )
}
