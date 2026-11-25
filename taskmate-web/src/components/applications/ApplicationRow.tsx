import { useState } from "react"
import { Star } from "lucide-react"
import { Link } from "react-router-dom"

import { ApplicationStatusBadge } from "@/components/applications/ApplicationStatusBadge"
import { StatusBadge } from "@/components/StatusBadge"
import { LeaveTeamButton } from "@/components/tasks/LeaveTeamButton"
import { TypeBadge } from "@/components/tasks/TypeBadge"
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
import { Card, CardContent } from "@/components/ui/card"
import { formatPostedDate, formatReward } from "@/lib/format"
import type { Application } from "@/types"

export function ApplicationRow({
  application,
  busy,
  onWithdraw,
  onLeave,
}: {
  application: Application
  busy: boolean
  onWithdraw: (id: number) => void
  onLeave: (id: number) => void
}) {
  const [confirmOpen, setConfirmOpen] = useState(false)
  const task = application.task

  if (!task) {
    return null
  }

  return (
    <Card>
      <CardContent className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="min-w-0 space-y-2">
          <div className="flex flex-wrap items-center gap-1.5">
            <TypeBadge type={task.type} />
            <StatusBadge status={task.status} />
            <ApplicationStatusBadge status={application.status} />
          </div>

          <Link
            to={`/tasks/${task.id}`}
            className="block truncate text-base font-semibold text-foreground hover:text-primary"
          >
            {task.title}
          </Link>

          <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
            <span className="font-medium text-foreground">
              {formatReward(task.type, task.reward)}
            </span>
            <span>
              {task.accepted_count} / {task.team_size} slots filled
            </span>
            <span>Applied {formatPostedDate(application.created_at)}</span>
            {application.accepted_at ? (
              <span>Accepted {formatPostedDate(application.accepted_at)}</span>
            ) : null}
          </div>
        </div>

        {task.status === "completed" && task.pending_reviews_count > 0 ? (
          <Button variant="outline" size="sm" asChild>
            <Link to={`/tasks/${task.id}`}>
              <Star className="size-4" />
              Review team
            </Link>
          </Button>
        ) : null}

        {application.status === "pending" ? (
          <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={busy}
              onClick={() => setConfirmOpen(true)}
            >
              Withdraw
            </Button>
            <AlertDialogContent>
              <AlertDialogHeader>
                <AlertDialogTitle>Withdraw application?</AlertDialogTitle>
                <AlertDialogDescription>
                  Your application to {task.title} will be removed. You can apply
                  again while the task is still open.
                </AlertDialogDescription>
              </AlertDialogHeader>
              <AlertDialogFooter>
                <AlertDialogCancel disabled={busy}>Keep application</AlertDialogCancel>
                <AlertDialogAction
                  variant="destructive"
                  disabled={busy}
                  onClick={(event) => {
                    event.preventDefault()
                    setConfirmOpen(false)
                    onWithdraw(application.id)
                  }}
                >
                  Withdraw
                </AlertDialogAction>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>
        ) : null}

        {application.status === "accepted" &&
        (task.status === "open" || task.status === "full") ? (
          <LeaveTeamButton
            busy={busy}
            onConfirm={() => onLeave(application.id)}
          />
        ) : null}
      </CardContent>
    </Card>
  )
}
