import axios from "axios"
import type { AxiosError } from "axios"

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

api.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiErrorBody>) => {
    const status = error.response?.status
    const url = error.config?.url ?? ""
    const isAuthRequest =
      url.includes("/auth/login") || url.includes("/auth/register")

    if (status === 401 && !isAuthRequest) {
      useAuthStore.getState().clearAuth()

      if (typeof window !== "undefined" && window.location.pathname !== "/login") {
        window.location.assign("/login")
      }
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
