import { useState } from "react"
import { Play } from "lucide-react"
import { Link } from "react-router-dom"

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
import { formatPoints } from "@/lib/format"
import { useAuthStore } from "@/stores/useAuthStore"
import { usePointsStore } from "@/stores/usePointsStore"
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
  const summary = usePointsStore((state) => state.summary)
  const fetchSummary = usePointsStore((state) => state.fetchSummary)
  const authBalance = useAuthStore((state) => state.user?.points_balance)

  const isPoints = task.type === "points"
  const required = isPoints
    ? Math.round(Number(task.reward)) * Math.max(1, task.accepted_count)
    : 0
  const balance = summary?.balance ?? authBalance ?? 0
  const insufficient = isPoints && balance < required

  function handleOpenChange(next: boolean) {
    setOpen(next)

    if (next && isPoints) {
      void fetchSummary()
    }
  }

  return (
    <AlertDialog open={open} onOpenChange={handleOpenChange}>
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

        {isPoints ? (
          <div className="space-y-1 rounded-lg border border-border bg-muted/30 p-3 text-sm">
            <p className="text-foreground">
              Required: {formatPoints(required)} points, Your balance: {formatPoints(balance)}
            </p>
            {insufficient ? (
              <p className="text-status-danger">
                Not enough points.{" "}
                <Link to="/points" className="font-medium underline">
                  Get more points
                </Link>
              </p>
            ) : null}
          </div>
        ) : null}

        <AlertDialogFooter>
          <AlertDialogCancel disabled={busy}>Not now</AlertDialogCancel>
          <AlertDialogAction
            disabled={busy || insufficient}
            onClick={(event) => {
              event.preventDefault()

              if (insufficient) {
                return
              }

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
