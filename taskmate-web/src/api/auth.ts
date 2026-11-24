import { api } from "@/api/client"
import type { ApiEnvelope, User } from "@/types"

export interface AuthResponse {
  user: User
  token: string
}

export interface LoginPayload {
  email: string
  password: string
}

export interface RegisterPayload extends LoginPayload {
  name: string
  country?: string
  password_confirmation: string
}

export interface VerifyEmailPayload {
  email: string
  code: string
}

export async function verifyEmail(payload: VerifyEmailPayload): Promise<User> {
  const { data } = await api.post<ApiEnvelope<User>>("/auth/email/verify", payload)
  return data.data
}

export async function resendVerification(): Promise<void> {
  await api.post("/auth/email/resend")
}

export async function login(payload: LoginPayload): Promise<AuthResponse> {
  const { data } = await api.post<ApiEnvelope<AuthResponse>>("/auth/login", payload)
  return data.data
}

export async function register(payload: RegisterPayload): Promise<AuthResponse> {
  const { data } = await api.post<ApiEnvelope<AuthResponse>>("/auth/register", payload)
  return data.data
}

export async function logout(): Promise<void> {
  await api.post("/auth/logout")
}

export async function me(): Promise<User> {
  const { data } = await api.get<ApiEnvelope<User>>("/auth/me")
  return data.data
}
