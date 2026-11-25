import { useState } from "react"
import { Loader2, MessageSquare } from "lucide-react"
import { useNavigate } from "react-router-dom"
import { toast } from "sonner"

import { getApiErrorMessage } from "@/api/client"
import { createConversation } from "@/api/messages"
import { Button } from "@/components/ui/button"
import { useChatStore } from "@/stores/useChatStore"

export function MessageButton({
  taskId,
  userId,
  label = "Message",
  variant = "outline",
  size = "sm",
}: {
  taskId: number
  userId: number
  label?: string
  variant?: "default" | "outline" | "secondary" | "ghost"
  size?: "default" | "sm"
}) {
  const navigate = useNavigate()
  const [busy, setBusy] = useState(false)

  async function handleClick() {
    setBusy(true)

    try {
      const conversation = await createConversation(taskId, userId)
      useChatStore.getState().upsertConversation(conversation)
      void useChatStore.getState().fetchConversations({ silent: true })
      navigate(`/messages/${conversation.id}`)
    } catch (error) {
      toast.error(getApiErrorMessage(error))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Button
      type="button"
      variant={variant}
      size={size}
      disabled={busy}
      onClick={() => void handleClick()}
    >
      {busy ? (
        <Loader2 className="size-4 animate-spin" />
      ) : (
        <MessageSquare className="size-4" />
      )}
      {busy ? "Opening..." : label}
    </Button>
  )
}
