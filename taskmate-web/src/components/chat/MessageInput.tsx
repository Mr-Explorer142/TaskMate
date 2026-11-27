import { useEffect, useRef, useState } from "react"
import { Loader2, Reply, Send, X } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Textarea } from "@/components/ui/textarea"
import type { Message } from "@/types"

const MAX_LENGTH = 2000

function previewOf(body: string): string {
  return body.length > 100 ? `${body.slice(0, 97)}...` : body
}

export function MessageInput({
  sending,
  replyTarget,
  replyLabel,
  onCancelReply,
  onSend,
}: {
  sending: boolean
  replyTarget: Message | null
  replyLabel: string | null
  onCancelReply: () => void
  onSend: (body: string, replyToId?: number | null) => Promise<void>
}) {
  const [value, setValue] = useState("")
  const composing = useRef(false)
  const composerRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (replyTarget) {
      composerRef.current?.querySelector("textarea")?.focus()
    }
  }, [replyTarget])

  async function submit() {
    const body = value.trim()

    if (body === "" || sending) {
      return
    }

    try {
      await onSend(body, replyTarget?.id)
      setValue("")
    } catch {
      // The parent surfaces the error toast and keeps the draft.
    }
  }

  return (
    <div ref={composerRef} className="space-y-2">
      {replyTarget ? (
        <div className="flex items-center gap-2 rounded-md border border-primary/30 bg-primary/5 px-2 py-1.5">
          <Reply className="size-4 shrink-0 text-primary" />
          <div className="min-w-0 flex-1">
            <p className="text-[11px] font-semibold text-primary">
              Replying to {replyLabel ?? "message"}
            </p>
            <p className="truncate text-xs text-muted-foreground">
              {previewOf(replyTarget.body)}
            </p>
          </div>
          <Button
            type="button"
            variant="ghost"
            size="icon"
            className="size-7"
            aria-label="Cancel reply"
            onClick={onCancelReply}
          >
            <X className="size-4" />
          </Button>
        </div>
      ) : null}

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
        placeholder={replyTarget ? "Write a reply..." : "Write a message..."}
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
