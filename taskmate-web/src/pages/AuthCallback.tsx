import { useEffect, useRef } from "react"
import { Loader2 } from "lucide-react"
import { useNavigate, useSearchParams } from "react-router-dom"
import { toast } from "sonner"

import { me } from "@/api/auth"
import { getApiErrorMessage } from "@/api/client"
import { useAuthStore } from "@/stores/useAuthStore"

export function AuthCallbackPage() {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const started = useRef(false)

  useEffect(() => {
    if (started.current) {
      return
    }

    started.current = true

    const token = searchParams.get("token")
    const socialError = searchParams.get("social_error")

    if (socialError) {
      toast.error(socialError)
      navigate("/login", { replace: true })
      return
    }

    if (!token) {
      toast.error("Sign-in could not be completed. Please try again.")
      navigate("/login", { replace: true })
      return
    }

    const { setAuth, setToken, clearAuth, setHydrating } = useAuthStore.getState()

    setToken(token)
    setHydrating(true)

    void me()
      .then((user) => {
        setAuth(user, token)
        toast.success(`Welcome, ${user.name}.`)
        navigate("/", { replace: true })
      })
      .catch((error) => {
        clearAuth()
        toast.error(getApiErrorMessage(error))
        navigate("/login", { replace: true })
      })
  }, [navigate, searchParams])

  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-3 bg-background px-4 text-center">
      <Loader2 className="size-6 animate-spin text-primary" />
      <p className="text-sm text-muted-foreground">Completing sign-in...</p>
    </div>
  )
}
