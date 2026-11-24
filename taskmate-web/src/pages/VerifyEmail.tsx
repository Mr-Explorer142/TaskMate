import { useCallback, useEffect, useRef, useState } from "react"
import type { FormEvent } from "react"
import { Loader2, MailCheck } from "lucide-react"
import { Link, useNavigate, useSearchParams } from "react-router-dom"
import { toast } from "sonner"

import { resendVerification, verifyEmail } from "@/api/auth"
import { getApiErrorMessage, getFieldErrors } from "@/api/client"
import { AuthShell } from "@/components/layout/AuthShell"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { useAuthStore } from "@/stores/useAuthStore"

export function VerifyEmailPage() {
  const [searchParams] = useSearchParams()
  const user = useAuthStore((state) => state.user)
  const setUser = useAuthStore((state) => state.setUser)
  const navigate = useNavigate()

  const queryEmail = searchParams.get("email") ?? ""
  const queryCode = searchParams.get("code") ?? ""

  const [email, setEmail] = useState(user?.email ?? queryEmail)
  const [code, setCode] = useState(queryCode.toUpperCase())
  const [submitting, setSubmitting] = useState(false)
  const [resending, setResending] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const autoSubmitted = useRef(false)

  const submit = useCallback(
    async (emailValue: string, codeValue: string) => {
      setSubmitting(true)
      setError(null)

      try {
        const verifiedUser = await verifyEmail({
          email: emailValue.trim(),
          code: codeValue.trim().toUpperCase(),
        })

        if (user) {
          setUser(verifiedUser)
        }

        toast.success("Your email has been verified.")
        navigate(user ? "/" : "/login", { replace: true })
      } catch (submitError) {
        const fieldError = getFieldErrors(submitError).code
        setError(fieldError ?? getApiErrorMessage(submitError))
      } finally {
        setSubmitting(false)
      }
    },
    [navigate, setUser, user]
  )

  useEffect(() => {
    if (autoSubmitted.current || !queryEmail || queryCode.length !== 6) {
      return
    }

    autoSubmitted.current = true
    void submit(queryEmail, queryCode)
  }, [queryEmail, queryCode, submit])

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    await submit(email, code)
  }

  async function handleResend() {
    setResending(true)

    try {
      await resendVerification()
      toast.success("A new verification code is on its way.")
    } catch (resendError) {
      toast.error(getApiErrorMessage(resendError))
    } finally {
      setResending(false)
    }
  }

  return (
    <AuthShell
      title="Verify your email"
      subtitle="Enter the 6-character code we emailed you."
      footer={
        user ? (
          <Link to="/" className="font-medium text-primary hover:underline">
            Back to TaskMate
          </Link>
        ) : (
          <>
            Already verified?{" "}
            <Link to="/login" className="font-medium text-primary hover:underline">
              Log in
            </Link>
          </>
        )
      }
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="flex justify-center">
          <span className="flex size-12 items-center justify-center rounded-full bg-secondary text-secondary-foreground">
            <MailCheck className="size-6" />
          </span>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="verify-email">Email</Label>
          <Input
            id="verify-email"
            type="email"
            autoComplete="email"
            placeholder="you@example.com"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            required
          />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="verify-code">Verification code</Label>
          <Input
            id="verify-code"
            inputMode="text"
            autoComplete="one-time-code"
            autoCapitalize="characters"
            placeholder="45XC78"
            maxLength={6}
            value={code}
            onChange={(event) => {
              setCode(event.target.value.toUpperCase().replace(/[^A-Z0-9]/g, ""))
              setError(null)
            }}
            className="text-center font-mono text-lg tracking-[0.4em]"
            required
          />
          {error ? <p className="text-sm text-destructive">{error}</p> : null}
        </div>

        <Button type="submit" className="w-full" disabled={submitting || code.length !== 6}>
          {submitting ? <Loader2 className="size-4 animate-spin" /> : null}
          {submitting ? "Verifying..." : "Verify email"}
        </Button>

        {user ? (
          <Button
            type="button"
            variant="ghost"
            className="w-full"
            disabled={resending}
            onClick={() => void handleResend()}
          >
            {resending ? <Loader2 className="size-4 animate-spin" /> : null}
            {resending ? "Sending..." : "Resend code"}
          </Button>
        ) : (
          <p className="text-center text-xs text-muted-foreground">
            Open the link from your email, or log in to resend the code.
          </p>
        )}
      </form>
    </AuthShell>
  )
}
