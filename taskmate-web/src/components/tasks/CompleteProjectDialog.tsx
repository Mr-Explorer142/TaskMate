import { useState } from "react"
import { CheckCircle2, XCircle } from "lucide-react"

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog"
import { Button } from "@/components/ui/button"
import type { Task } from "@/types"

export function CompleteProjectDialog({
  task,
  busy = false,
  onConfirm,
  onCancel,
}: {
  task: Task
  busy?: boolean
  onConfirm: () => void
  onCancel: () => void
}) {
  const [open, setOpen] = useState(false)
  const [confirmingCancel, setConfirmingCancel] = useState(false)
  const reward = Math.round(Number(task.reward))
  const total = reward * task.accepted_count

  function close(nextOpen: boolean) {
    setOpen(nextOpen)

    if (!nextOpen) {
      setConfirmingCancel(false)
    }
  }

  return (
    <AlertDialog open={open} onOpenChange={close}>
      <AlertDialogTrigger asChild>
        <Button size="sm" disabled={busy}>
          <CheckCircle2 className="size-4" />
          Complete project
        </Button>
      </AlertDialogTrigger>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>
            {confirmingCancel ? "Cancel this project?" : "Complete this project?"}
          </AlertDialogTitle>
          <AlertDialogDescription>
            {confirmingCancel
              ? "This permanently closes the project. This cannot be undone."
              : "This marks the project as finished. Every accepted member can then review the team."}
          </AlertDialogDescription>
        </AlertDialogHeader>

        <div
          className={`rounded-lg border p-3 text-sm ${
            confirmingCancel
              ? "border-destructive/30 bg-destructive/10 text-destructive"
              : "border-border bg-muted/30 text-muted-foreground"
          }`}
        >
          {confirmingCancel ? (
            task.type === "points" ? (
              <>
                Escrowed points will be refunded to you and members get nothing.
                This cannot be undone.
              </>
            ) : (
              <>Members will receive nothing and payment is handled outside TaskMate.</>
            )
          ) : task.type === "points" ? (
            <>
              {reward.toLocaleString("en-US")} points will be released to each of{" "}
              {task.accepted_count} member{task.accepted_count === 1 ? "" : "s"}{" "}
              (total {total.toLocaleString("en-US")}). Any leftover escrow is
              refunded to you.
            </>
          ) : (
            <>Payment is handled outside TaskMate.</>
          )}
        </div>

        <AlertDialogFooter>
          {confirmingCancel ? (
            <>
              <Button
                type="button"
                variant="outline"
                disabled={busy}
                onClick={() => setConfirmingCancel(false)}
              >
                Back
              </Button>
              <AlertDialogAction
                className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                disabled={busy}
                onClick={(event) => {
                  event.preventDefault()
                  close(false)
                  onCancel()
                }}
              >
                Cancel project
              </AlertDialogAction>
            </>
          ) : (
            <>
              <AlertDialogCancel disabled={busy}>Not yet</AlertDialogCancel>
              <div className="flex flex-wrap justify-end gap-2">
                <Button
                  type="button"
                  variant="destructive"
                  size="sm"
                  disabled={busy}
                  onClick={() => setConfirmingCancel(true)}
                >
                  <XCircle className="size-4" />
                  Cancel project
                </Button>
                <AlertDialogAction
                  disabled={busy}
                  onClick={(event) => {
                    event.preventDefault()
                    setOpen(false)
                    onConfirm()
                  }}
                >
                  Complete project
                </AlertDialogAction>
              </div>
            </>
          )}
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
