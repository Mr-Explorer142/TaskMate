import { useRef, useState } from "react"
import { Loader2, Send } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Textarea } from "@/components/ui/textarea"

const MAX_LENGTH = 2000

export function MessageInput({
  sending,
  onSend,
}: {
  sending: boolean
  onSend: (body: string) => Promise<void>
}) {
  const [value, setValue] = useState("")
  const composing = useRef(false)

  async function submit() {
    const body = value.trim()

    if (body === "" || sending) {
      return
    }

    try {
      await onSend(body)
      setValue("")
    } catch {
      // The parent surfaces the error toast and keeps the draft.
    }
  }

  return (
    <div className="space-y-2">
      <Textarea
        aria-label="Message"
        value={value}
        onChange={(event) => setValue(event.target.value.slice(0, MAX_LENGTH))}
        onKeyDown={(event) => {
          if (
            event.key === "Enter" &&
            !event.shiftKey &&
            !composing.current &&
            !event.nativeEvent.isComposing
          ) {
            event.preventDefault()
            void submit()
          }
        }}
        onCompositionStart={() => {
          composing.current = true
        }}
        onCompositionEnd={() => {
          composing.current = false
        }}
        maxLength={MAX_LENGTH}
        rows={2}
        placeholder="Write a message..."
        disabled={sending}
        className="max-h-40 min-h-16 resize-none"
      />

      <div className="flex items-center justify-between gap-3">
        <span className="text-xs text-muted-foreground">
          {value.length}/{MAX_LENGTH}
        </span>
        <Button
          type="button"
          size="sm"
          onClick={() => void submit()}
          disabled={sending || value.trim() === ""}
        >
          {sending ? (
            <Loader2 className="size-4 animate-spin" />
          ) : (
            <Send className="size-4" />
          )}
          Send
        </Button>
      </div>
    </div>
  )
}
