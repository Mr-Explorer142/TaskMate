import { useState } from "react"
import { Loader2, MailWarning } from "lucide-react"
import { Link } from "react-router-dom"
import { toast } from "sonner"

import { resendVerification } from "@/api/auth"
import { getApiErrorMessage } from "@/api/client"
import { Button } from "@/components/ui/button"
import { useAuthStore } from "@/stores/useAuthStore"

export function VerifyEmailBanner() {
  const user = useAuthStore((state) => state.user)
  const [sending, setSending] = useState(false)

  if (!user || user.email_verified) {
    return null
  }

  async function handleResend() {
    setSending(true)

    try {
      await resendVerification()
      toast.success("A new verification code is on its way.")
    } catch (error) {
      toast.error(getApiErrorMessage(error))
    } finally {
      setSending(false)
    }
  }

  return (
    <div className="border-b border-amber-200 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/10">
      <div className="mx-auto flex w-full max-w-5xl flex-col items-start gap-2 px-4 py-2.5 text-sm sm:flex-row sm:items-center sm:justify-between">
        <span className="flex items-center gap-2 text-amber-900 dark:text-amber-200">
          <MailWarning className="size-4 shrink-0" />
          Verify your email to secure your account.
        </span>
        <span className="flex items-center gap-2">
          <Button asChild size="sm">
            <Link to="/verify-email">Enter code</Link>
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            disabled={sending}
            onClick={() => void handleResend()}
          >
            {sending ? <Loader2 className="size-4 animate-spin" /> : null}
            {sending ? "Sending..." : "Resend"}
          </Button>
        </span>
      </div>
    </div>
  )
}
