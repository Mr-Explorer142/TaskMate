import { useState } from "react"
import { Play } from "lucide-react"

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

export function StartProjectDialog({
  task,
  busy = false,
  disabled = false,
  onConfirm,
}: {
  task: Task
  busy?: boolean
  disabled?: boolean
  onConfirm: () => void
}) {
  const [open, setOpen] = useState(false)

  return (
    <AlertDialog open={open} onOpenChange={setOpen}>
      <AlertDialogTrigger asChild>
        <Button size="sm" disabled={busy || disabled}>
          <Play className="size-4" />
          Start project
        </Button>
      </AlertDialogTrigger>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Start this project?</AlertDialogTitle>
          <AlertDialogDescription>
            {task.accepted_count} of {task.team_size} team slots are filled.
            Pending applications will be rejected and the team will be locked.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel disabled={busy}>Not yet</AlertDialogCancel>
          <AlertDialogAction
            disabled={busy}
            onClick={(event) => {
              event.preventDefault()
              setOpen(false)
              onConfirm()
            }}
          >
            Start project
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
