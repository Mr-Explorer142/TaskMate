import { useRef, useState, type TouchEvent } from "react"
import {
  Check,
  Loader2,
  MoreHorizontal,
  Pencil,
  Reply,
  Trash2,
  X,
} from "lucide-react"

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSub,
  DropdownMenuSubContent,
  DropdownMenuSubTrigger,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Textarea } from "@/components/ui/textarea"
import { formatTime } from "@/lib/format"
import { useAuthStore } from "@/stores/useAuthStore"
import type { Message, MessageDeleteScope } from "@/types"

interface MessageBubbleProps {
  message: Message
  otherName: string
  onReply: (message: Message) => void
  onEdit: (message: Message, body: string) => Promise<void>
  onDelete: (message: Message, scope: MessageDeleteScope) => Promise<void>
}

const SWIPE_THRESHOLD = 48

function previewOf(body: string): string {
  return body.length > 80 ? `${body.slice(0, 77)}...` : body
}

export function MessageBubble({
  message,
  otherName,
  onReply,
  onEdit,
  onDelete,
}: MessageBubbleProps) {
  const ownId = useAuthStore((state) => state.user?.id)
  const isOwn = message.pending || message.sender_id === ownId
  const isDeleted =
    message.is_deleted_for_everyone || message.is_deleted_for_me
  const canAct = !message.pending && !isDeleted

  const [editing, setEditing] = useState(false)
  const [draft, setDraft] = useState(message.body)
  const [busy, setBusy] = useState(false)
  const [deleteScope, setDeleteScope] = useState<MessageDeleteScope | null>(null)
  const [swipeOffset, setSwipeOffset] = useState(0)
  const touchStart = useRef<{ x: number; y: number } | null>(null)

  function startEdit() {
    setDraft(message.body)
    setEditing(true)
  }

  async function saveEdit() {
    const body = draft.trim()

    if (body === "" || body === message.body) {
      setEditing(false)
      return
    }

    setBusy(true)

    try {
      await onEdit(message, body)
      setEditing(false)
    } catch {
      // The parent surfaces the error toast and keeps the original message.
    } finally {
      setBusy(false)
    }
  }

  async function confirmDelete() {
    if (deleteScope === null) {
      return
    }

    const scope = deleteScope
    setBusy(true)

    try {
      await onDelete(message, scope)
    } catch {
      // The parent surfaces the error toast and restores the message.
    } finally {
      setBusy(false)
      setDeleteScope(null)
    }
  }

  function handleTouchStart(event: TouchEvent<HTMLDivElement>) {
    if (!canAct || editing) {
      return
    }

    const touch = event.touches[0]

    if (!touch) {
      return
    }

    touchStart.current = { x: touch.clientX, y: touch.clientY }
  }

  function handleTouchMove(event: TouchEvent<HTMLDivElement>) {
    const start = touchStart.current
    const touch = event.touches[0]

    if (!start || !touch) {
      return
    }

    const deltaX = touch.clientX - start.x
    const deltaY = touch.clientY - start.y

    if (deltaX < 0 && Math.abs(deltaX) > Math.abs(deltaY)) {
      setSwipeOffset(Math.min(72, Math.max(0, -deltaX)))
    }
  }

  function handleTouchEnd() {
    if (swipeOffset >= SWIPE_THRESHOLD) {
      onReply(message)
    }

    touchStart.current = null
    setSwipeOffset(0)
  }

  const replyLabel = message.reply_to
    ? message.reply_to.sender_id === ownId
      ? "you"
      : otherName
    : null

  const actions = canAct ? (
    <div className="flex shrink-0 items-center gap-0.5 self-end pb-0.5">
      <Button
        type="button"
        variant="ghost"
        size="icon"
        aria-label="Reply to message"
        title="Reply"
        className="size-8 rounded-full text-muted-foreground hover:text-foreground"
        onClick={() => onReply(message)}
      >
        <Reply className="size-4" />
      </Button>

      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button
            type="button"
            variant="ghost"
            size="icon"
            aria-label="Message actions"
            className="size-8 rounded-full text-muted-foreground hover:text-foreground"
          >
            <MoreHorizontal className="size-4" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align={isOwn ? "end" : "start"} className="min-w-44">
          {isOwn ? (
            <DropdownMenuItem onSelect={startEdit}>
              <Pencil />
              Edit
            </DropdownMenuItem>
          ) : null}

          {isOwn ? (
            <DropdownMenuSub>
              <DropdownMenuSubTrigger>
                <Trash2 />
                Delete
              </DropdownMenuSubTrigger>
              <DropdownMenuSubContent>
                <DropdownMenuItem onSelect={() => setDeleteScope("me")}>
                  Delete for me
                </DropdownMenuItem>
                <DropdownMenuItem
                  variant="destructive"
                  onSelect={() => setDeleteScope("everyone")}
                >
                  Delete for everyone
                </DropdownMenuItem>
              </DropdownMenuSubContent>
            </DropdownMenuSub>
          ) : (
            <DropdownMenuItem
              variant="destructive"
              onSelect={() => setDeleteScope("me")}
            >
              <Trash2 />
              Delete for me
            </DropdownMenuItem>
          )}
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  ) : null

  return (
    <div
      className="relative"
      onTouchStart={handleTouchStart}
      onTouchMove={handleTouchMove}
      onTouchEnd={handleTouchEnd}
      style={{ touchAction: "pan-y" }}
    >
      <div
        aria-hidden={swipeOffset === 0}
        className="absolute inset-y-0 right-1 flex items-center gap-1 text-xs font-medium text-primary transition-opacity"
        style={{ opacity: swipeOffset > 0 ? 1 : 0 }}
      >
        <Reply className="size-4" />
        Reply
      </div>

      <div
        className={`flex items-end gap-1 ${isOwn ? "justify-end" : "justify-start"}`}
        style={{ transform: `translateX(-${swipeOffset}px)` }}
      >
        {isOwn ? actions : null}

        <div className={`flex max-w-[78%] flex-col ${isOwn ? "items-end" : "items-start"}`}>
          <div
            className={[
              "w-full whitespace-pre-wrap break-words rounded-lg px-3 py-2 text-sm",
              isOwn
                ? "bg-primary text-primary-foreground"
                : "border border-border bg-muted text-foreground",
              message.pending ? "opacity-70" : "",
              isDeleted ? "italic opacity-70" : "",
            ].join(" ")}
          >
            {message.reply_to ? (
              <div
                className={[
                  "mb-2 rounded border-l-2 px-2 py-1 text-xs",
                  isOwn
                    ? "border-primary-foreground/60 bg-primary-foreground/10"
                    : "border-primary/60 bg-background/60",
                ].join(" ")}
              >
                <p className="font-semibold">Replying to {replyLabel}</p>
                <p className="truncate opacity-80">
                  {previewOf(message.reply_to.body)}
                </p>
              </div>
            ) : null}

            {editing ? (
              <div className="w-64 max-w-full space-y-2 sm:w-80">
                <Textarea
                  aria-label="Edit message"
                  value={draft}
                  maxLength={2000}
                  rows={3}
                  disabled={busy}
                  onChange={(event) => setDraft(event.target.value)}
                  onKeyDown={(event) => {
                    if (
                      event.key === "Escape" &&
                      !event.nativeEvent.isComposing
                    ) {
                      event.preventDefault()
                      setEditing(false)
                    }

                    if (
                      event.key === "Enter" &&
                      (event.metaKey || event.ctrlKey) &&
                      !event.nativeEvent.isComposing
                    ) {
                      event.preventDefault()
                      void saveEdit()
                    }
                  }}
                  className="max-h-40 resize-none"
                />
                <div className="flex justify-end gap-1">
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    disabled={busy}
                    aria-label="Cancel edit"
                    onClick={() => setEditing(false)}
                  >
                    <X className="size-4" />
                  </Button>
                  <Button
                    type="button"
                    size="icon"
                    className="size-8"
                    disabled={busy || draft.trim() === ""}
                    aria-label="Save edit"
                    onClick={() => void saveEdit()}
                  >
                    {busy ? (
                      <Loader2 className="size-4 animate-spin" />
                    ) : (
                      <Check className="size-4" />
                    )}
                  </Button>
                </div>
              </div>
            ) : (
              <p>{message.body}</p>
            )}
          </div>

          <span className="mt-1 px-1 text-[11px] text-muted-foreground">
            {message.pending
              ? "Sending..."
              : `${formatTime(message.created_at)}${message.edited_at ? " · Edited" : ""}`}
          </span>
        </div>

        {isOwn ? null : actions}
      </div>

      <AlertDialog
        open={deleteScope !== null}
        onOpenChange={(open) => {
          if (!open) {
            setDeleteScope(null)
          }
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {deleteScope === "everyone" ? "Delete for everyone?" : "Delete for me?"}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {deleteScope === "everyone"
                ? "This removes the message for both people in the conversation. This cannot be undone."
                : "This hides the message only from your view. The other person can still see it."}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={busy}>Cancel</AlertDialogCancel>
            <AlertDialogAction
              variant="destructive"
              disabled={busy}
              onClick={(event) => {
                event.preventDefault()
                void confirmDelete()
              }}
            >
              {busy ? <Loader2 className="size-4 animate-spin" /> : null}
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
