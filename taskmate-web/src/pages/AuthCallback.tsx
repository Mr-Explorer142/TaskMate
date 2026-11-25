import { useEffect, useRef, useState } from "react"
import { Loader2 } from "lucide-react"
import { Link, useNavigate, useSearchParams } from "react-router-dom"

import { me } from "@/api/auth"
import { getApiErrorMessage } from "@/api/client"
import { ErrorState } from "@/components/PageStates"
import { Button } from "@/components/ui/button"
import { usePageTitle } from "@/hooks/usePageTitle"
import { useAuthStore } from "@/stores/useAuthStore"

export function AuthCallbackPage() {
  usePageTitle("Signing in")

  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const [error, setError] = useState<string | null>(null)
  const [attempt, setAttempt] = useState(0)
  const started = useRef(-1)

  useEffect(() => {
    if (started.current === attempt) {
      return
    }

    started.current = attempt

    const token = searchParams.get("token")
    const socialError = searchParams.get("social_error")

    if (socialError) {
      setError(socialError)
      return
    }

    if (!token) {
      setError("Sign-in could not be completed. Please try again.")
      return
    }

    const { setAuth, setToken, clearAuth, setHydrating } = useAuthStore.getState()

    setToken(token)
    setHydrating(true)

    void me()
      .then((user) => {
        setAuth(user, token)
        navigate("/", { replace: true })
      })
      .catch((caught) => {
        clearAuth()
        setError(getApiErrorMessage(caught))
      })
      .finally(() => {
        setHydrating(false)
      })
  }, [attempt, navigate, searchParams])

  if (error) {
    return (
      <div className="mx-auto flex min-h-screen max-w-lg flex-col items-center justify-center gap-4 px-4">
        <ErrorState
          title="Sign-in failed"
          message={error}
          onRetry={
            searchParams.get("token")
              ? () => {
                  setError(null)
                  setAttempt((value) => value + 1)
                }
              : undefined
          }
        />
        <Button asChild variant="outline">
          <Link to="/login">Go to login</Link>
        </Button>
      </div>
    )
  }

  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-3 bg-background px-4 text-center">
      <Loader2 className="size-6 animate-spin text-primary" />
      <p className="text-sm text-muted-foreground">Completing sign-in...</p>
    </div>
  )
}
