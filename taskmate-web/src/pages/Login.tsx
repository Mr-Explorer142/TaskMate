import { useState } from "react"
import { zodResolver } from "@hookform/resolvers/zod"
import { AlertCircle, Loader2 } from "lucide-react"
import { useForm } from "react-hook-form"
import { Link, useLocation, useNavigate, useSearchParams } from "react-router-dom"
import { toast } from "sonner"
import { z } from "zod"

import { login } from "@/api/auth"
import { getApiErrorMessage, getFieldErrors } from "@/api/client"
import { AuthShell } from "@/components/layout/AuthShell"
import { SocialAuthButtons } from "@/components/SocialAuthButtons"
import { Button } from "@/components/ui/button"
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form"
import { Input } from "@/components/ui/input"
import { usePageTitle } from "@/hooks/usePageTitle"
import { useAuthStore } from "@/stores/useAuthStore"

const loginSchema = z.object({
  email: z.string().min(1, "Email is required.").email("Enter a valid email address."),
  password: z.string().min(1, "Password is required."),
})

type LoginValues = z.infer<typeof loginSchema>

export function LoginPage() {
  usePageTitle("Log in")

  const setAuth = useAuthStore((state) => state.setAuth)
  const navigate = useNavigate()
  const location = useLocation()
  const [searchParams] = useSearchParams()
  const [submitting, setSubmitting] = useState(false)

  const redirect = searchParams.get("redirect")
  const sessionExpired = searchParams.get("reason") === "session_expired"
  const safeRedirect =
    redirect && redirect.startsWith("/") && !redirect.startsWith("//")
      ? redirect
      : null

  const form = useForm<LoginValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: "", password: "" },
  })

  const from =
    safeRedirect ?? (location.state as { from?: string } | null)?.from ?? "/"

  async function onSubmit(values: LoginValues) {
    setSubmitting(true)

    try {
      const result = await login(values)
      setAuth(result.user, result.token)
      toast.success(`Welcome back, ${result.user.name}.`)
      navigate(from, { replace: true })
    } catch (error) {
      const fieldErrors = getFieldErrors(error)

      if (Object.keys(fieldErrors).length > 0) {
        for (const [field, message] of Object.entries(fieldErrors)) {
          if (field === "email" || field === "password") {
            form.setError(field, { message })
          }
        }
      }

      toast.error(getApiErrorMessage(error))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <AuthShell
      title="Welcome back"
      subtitle="Log in to manage your profile and find your next team."
      footer={
        <>
          New to TaskMate?{" "}
          <Link to="/register" className="font-medium text-primary hover:underline">
            Create an account
          </Link>
        </>
      }
    >
      {sessionExpired ? (
        <div
          role="alert"
          className="mb-4 flex gap-2 rounded-lg border border-primary/20 bg-primary/5 p-3 text-sm text-foreground"
        >
          <AlertCircle className="size-4 shrink-0 text-primary" />
          <p>Your session expired. Please log in again.</p>
        </div>
      ) : null}

      <SocialAuthButtons />

      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="mt-5 space-y-4">
          <FormField
            control={form.control}
            name="email"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Email</FormLabel>
                <FormControl>
                  <Input
                    type="email"
                    autoComplete="email"
                    placeholder="you@example.com"
                    {...field}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="password"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Password</FormLabel>
                <FormControl>
                  <Input
                    type="password"
                    autoComplete="current-password"
                    placeholder="••••••••"
                    {...field}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          <Button type="submit" className="w-full" disabled={submitting}>
            {submitting ? <Loader2 className="size-4 animate-spin" /> : null}
            {submitting ? "Logging in..." : "Log in"}
          </Button>
        </form>
      </Form>
    </AuthShell>
  )
}
