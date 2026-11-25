import { useCallback, useEffect, useState } from "react"
import axios from "axios"
import { ArrowLeft, CalendarDays, Pencil, PlayCircle, Star, Trash2, Users, XCircle } from "lucide-react"
import { Link, useNavigate, useParams } from "react-router-dom"
import { toast } from "sonner"

import { getApiErrorMessage } from "@/api/client"
import { getTask, getTaskTeam } from "@/api/tasks"
import { ApplicationStatusBadge } from "@/components/applications/ApplicationStatusBadge"
import { ApplyDialog } from "@/components/applications/ApplyDialog"
import { ErrorState } from "@/components/PageStates"
import { StatusBadge } from "@/components/StatusBadge"
import { LeaveTeamButton } from "@/components/tasks/LeaveTeamButton"
import { SlotsIndicator } from "@/components/tasks/SlotsIndicator"
import { TeamRoster } from "@/components/tasks/TeamRoster"
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
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Skeleton } from "@/components/ui/skeleton"
import { formatDeadline, formatPostedDate, formatReward } from "@/lib/format"
import { getInitials } from "@/lib/utils"
import { useApplicationStore } from "@/stores/useApplicationStore"
import { useTaskStore } from "@/stores/useTaskStore"
import type { Task, TaskStatus, Team } from "@/types"

function TaskDetailSkeleton() {  return (
    <div className="mx-auto max-w-3xl space-y-4">
      <Skeleton className="h-4 w-24" />
      <Skeleton className="h-8 w-3/4" />
      <div className="flex gap-2">
        <Skeleton className="h-5 w-16 rounded-full" />
        <Skeleton className="h-5 w-14 rounded-full" />
      </div>
      <Skeleton className="h-40 w-full rounded-lg" />
      <Skeleton className="h-28 w-full rounded-lg" />
    </div>
  )
}

function closedLabel(status: TaskStatus): string {
  switch (status) {
    case "full":
      return "Team is full"
    case "cancelled":
      return "Task cancelled"
    case "completed":
      return "Task completed"
    case "in_progress":
      return "Task in progress"
    default:
      return "Applications closed"
  }
}

export function TaskDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const cancelTask = useTaskStore((state) => state.cancelTask)
  const removeTask = useTaskStore((state) => state.removeTask)
  const applyToTask = useApplicationStore((state) => state.apply)
  const withdrawApplication = useApplicationStore((state) => state.withdraw)
  const leaveTeam = useApplicationStore((state) => state.leave)
  const [task, setTask] = useState<Task | null>(null)
  const [team, setTeam] = useState<Team | null>(null)
  const [loading, setLoading] = useState(true)
  const [teamLoading, setTeamLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [teamError, setTeamError] = useState<string | null>(null)
  const [notFound, setNotFound] = useState(false)
  const [busy, setBusy] = useState(false)
  const [applyOpen, setApplyOpen] = useState(false)
  const [applicationBusy, setApplicationBusy] = useState(false)

  const loadTask = useCallback(async () => {
    if (!id) {
      setNotFound(true)
      setLoading(false)
      return
    }

    setLoading(true)
    setError(null)
    setNotFound(false)

    try {
      const result = await getTask(Number(id))
      setTask(result)
    } catch (caught) {
      if (axios.isAxiosError(caught) && caught.response?.status === 404) {
        setNotFound(true)
      } else {
        setError(getApiErrorMessage(caught))
      }
    } finally {
      setLoading(false)
    }
  }, [id])

  const loadTeam = useCallback(async () => {
    if (!id) {
      setTeamLoading(false)
      return
    }

    setTeamLoading(true)
    setTeamError(null)

    try {
      setTeam(await getTaskTeam(Number(id)))
    } catch (caught) {
      setTeamError(getApiErrorMessage(caught))
    } finally {
      setTeamLoading(false)
    }
  }, [id])

  useEffect(() => {
    void loadTask()
  }, [loadTask])

  useEffect(() => {
    void loadTeam()
  }, [loadTeam])

  async function handleCancel() {
    if (!task) {
      return
    }

    setBusy(true)

    try {
      const updated = await cancelTask(task.id)
      setTask(updated)
      toast.success("Task cancelled.")
    } catch (caught) {
      toast.error(getApiErrorMessage(caught))
    } finally {
      setBusy(false)
    }
  }

  async function handleDelete() {
    if (!task) {
      return
    }

    setBusy(true)

    try {
      await removeTask(task.id)
      toast.success("Task deleted.")
      navigate("/my-tasks", { replace: true })
    } catch (caught) {
      toast.error(getApiErrorMessage(caught))
      setBusy(false)
    }
  }

  async function handleApply(message: string | null) {
    if (!task) {
      return
    }

    setApplicationBusy(true)

    try {
      const application = await applyToTask(task.id, message)
      setTask({
        ...task,
        my_application_id: application.id,
        my_application_status: application.status,
      })
      setApplyOpen(false)
      toast.success("Application submitted.")
    } catch (caught) {
      toast.error(getApiErrorMessage(caught))
    } finally {
      setApplicationBusy(false)
    }
  }

  async function handleWithdraw() {
    if (!task?.my_application_id) {
      return
    }

    setApplicationBusy(true)

    try {
      await withdrawApplication(task.my_application_id)
      setTask({
        ...task,
        my_application_id: null,
        my_application_status: null,
      })
      toast.success("Application withdrawn.")
    } catch (caught) {
      toast.error(getApiErrorMessage(caught))
    } finally {
      setApplicationBusy(false)
    }
  }

  async function handleLeave() {
    if (!task?.my_application_id) {
      return
    }

    setApplicationBusy(true)

    try {
      await leaveTeam(task.my_application_id)
      toast.success("You have left the team.")
      await Promise.all([loadTask(), loadTeam()])
    } catch (caught) {
      toast.error(getApiErrorMessage(caught))
    } finally {
      setApplicationBusy(false)
    }
  }

  if (loading) {
    return <TaskDetailSkeleton />
  }

  if (notFound) {
    return (
      <div className="mx-auto max-w-3xl space-y-4">
        <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed border-border bg-muted/30 px-6 py-12 text-center">
          <p className="text-lg font-semibold text-foreground">Task not found</p>
          <p className="text-sm text-muted-foreground">
            This task may have been deleted or never existed.
          </p>
          <Button variant="outline" asChild>
            <Link to="/tasks">Back to tasks</Link>
          </Button>
        </div>
      </div>
    )
  }

  if (error || !task) {
    return (
      <div className="mx-auto max-w-3xl">
        <ErrorState
          title="Could not load this task"
          message={error ?? "Something went wrong."}
          onRetry={() => void loadTask()}
        />
      </div>
    )
  }

  const isOwner = task.is_owner
  const isInProgress = task.status === "in_progress"
  const canEdit = isOwner && (task.status === "open" || task.status === "full")
  const canCancel = canEdit
  const canDelete = isOwner && (task.status === "open" || task.status === "cancelled")
  const rating =
    task.uploader?.average_rating != null
      ? Number(task.uploader.average_rating).toFixed(1)
      : null

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <Button variant="ghost" size="sm" asChild className="-ml-2">
        <Link to={isOwner ? "/my-tasks" : "/tasks"}>
          <ArrowLeft className="size-4" />
          {isOwner ? "My tasks" : "All tasks"}
        </Link>
      </Button>

      <div className="space-y-3">
        <div className="flex flex-wrap items-center gap-2">
          <TypeBadge type={task.type} />
          <StatusBadge status={task.status} />
        </div>
        <h1 className="text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
          {task.title}
        </h1>
        <div className="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-muted-foreground">
          <span className="flex items-center gap-1.5 font-medium text-foreground">
            {formatReward(task.type, task.reward)}
          </span>
          <span className="flex items-center gap-1.5">
            <CalendarDays className="size-4" />
            {formatDeadline(task.deadline)}
          </span>
          <span className="flex items-center gap-1.5">
            <Users className="size-4" />
            {task.team_size} {task.team_size === 1 ? "person" : "people"} needed
          </span>
        </div>
      </div>

      {isInProgress ? (
        <div className="flex items-start gap-3 rounded-lg border border-status-in-progress/20 bg-status-in-progress-bg p-4 text-status-in-progress">
          <PlayCircle className="mt-0.5 size-5 shrink-0" />
          <div className="space-y-0.5">
            <p className="text-sm font-semibold">Project in progress</p>
            <p className="text-sm">
              {task.started_at
                ? `Started on ${formatPostedDate(task.started_at)}. The team is locked.`
                : "The team is locked."}
            </p>
          </div>
        </div>
      ) : null}

      {isOwner ? (
        <div className="flex flex-wrap gap-2">
          <Button variant="outline" size="sm" asChild>
            <Link to={`/tasks/${task.id}/applicants`}>
              <Users className="size-4" />
              Applicants
              {(task.pending_count ?? 0) > 0 ? (
                <Badge variant="secondary">{task.pending_count}</Badge>
              ) : null}
            </Link>
          </Button>

          {canEdit ? (
            <Button variant="outline" size="sm" asChild>
              <Link to={`/tasks/${task.id}/edit`}>
                <Pencil className="size-4" />
                Edit
              </Link>
            </Button>
          ) : null}

          {canCancel ? (
            <AlertDialog>
              <AlertDialogTrigger asChild>
                <Button variant="outline" size="sm" disabled={busy}>
                  <XCircle className="size-4" />
                  Cancel task
                </Button>
              </AlertDialogTrigger>
              <AlertDialogContent>
                <AlertDialogHeader>
                  <AlertDialogTitle>Cancel this task?</AlertDialogTitle>
                  <AlertDialogDescription>
                    Pending applications will be rejected. This cannot be undone.
                  </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                  <AlertDialogCancel>Keep task</AlertDialogCancel>
                  <AlertDialogAction
                    onClick={() => {
                      void handleCancel()
                    }}
                  >
                    Cancel task
                  </AlertDialogAction>
                </AlertDialogFooter>
              </AlertDialogContent>
            </AlertDialog>
          ) : null}

          {canDelete ? (
            <AlertDialog>
              <AlertDialogTrigger asChild>
                <Button variant="destructive" size="sm" disabled={busy}>
                  <Trash2 className="size-4" />
                  Delete
                </Button>
              </AlertDialogTrigger>
              <AlertDialogContent>
                <AlertDialogHeader>
                  <AlertDialogTitle>Delete this task?</AlertDialogTitle>
                  <AlertDialogDescription>
                    This permanently removes the task. Tasks with accepted members cannot be
                    deleted.
                  </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                  <AlertDialogCancel>Keep task</AlertDialogCancel>
                  <AlertDialogAction
                    className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    onClick={() => {
                      void handleDelete()
                    }}
                  >
                    Delete task
                  </AlertDialogAction>
                </AlertDialogFooter>
              </AlertDialogContent>
            </AlertDialog>
          ) : null}
        </div>
      ) : isInProgress ? (
        task.my_application_status === "accepted" ? (
          <ApplicationStatusBadge
            status="accepted"
            label="You are on this team"
          />
        ) : null
      ) : task.my_application_status === "pending" ? (
        <div className="flex flex-wrap items-center gap-2">
          <ApplicationStatusBadge status="pending" />
          <AlertDialog>
            <AlertDialogTrigger asChild>
              <Button
                variant="outline"
                size="sm"
                disabled={applicationBusy}
              >
                Withdraw application
              </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
              <AlertDialogHeader>
                <AlertDialogTitle>Withdraw your application?</AlertDialogTitle>
                <AlertDialogDescription>
                  Your application will be removed. You can apply again while the
                  task is still open.
                </AlertDialogDescription>
              </AlertDialogHeader>
              <AlertDialogFooter>
                <AlertDialogCancel disabled={applicationBusy}>
                  Keep it
                </AlertDialogCancel>
                <AlertDialogAction
                  variant="destructive"
                  disabled={applicationBusy}
                  onClick={(event) => {
                    event.preventDefault()
                    void handleWithdraw()
                  }}
                >
                  Withdraw
                </AlertDialogAction>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>
        </div>
      ) : task.my_application_status === "accepted" ? (
        <div className="flex flex-wrap items-center gap-2">
          <ApplicationStatusBadge
            status="accepted"
            label="You are on this team"
          />
          {task.can_leave ? (
            <LeaveTeamButton
              busy={applicationBusy}
              onConfirm={() => void handleLeave()}
            />
          ) : null}
        </div>
      ) : task.my_application_status === "rejected" ? (
        <ApplicationStatusBadge status="rejected" label="Not selected" />
      ) : task.my_application_status === "left" ? (
        <ApplicationStatusBadge status="left" />
      ) : task.status === "open" ? (
        <div className="flex flex-col gap-1.5 sm:items-start">
          <Button
            onClick={() => setApplyOpen(true)}
            disabled={applicationBusy}
            className="sm:w-auto"
          >
            Apply to this task
          </Button>
          <p className="text-xs text-muted-foreground">
            {task.open_slots} {task.open_slots === 1 ? "slot" : "slots"} left on
            this team.
          </p>
        </div>
      ) : (
        <div className="flex flex-col gap-1.5 sm:items-start">
          <Button disabled className="sm:w-auto">
            {closedLabel(task.status)}
          </Button>
        </div>
      )}

      <ApplyDialog
        open={applyOpen}
        onOpenChange={setApplyOpen}
        taskTitle={task.title}
        submitting={applicationBusy}
        onSubmit={(message) => void handleApply(message)}
      />

      <Card>
        <CardHeader>
          <CardTitle>About this task</CardTitle>
        </CardHeader>
        <CardContent className="space-y-5">
          <p className="whitespace-pre-line text-sm leading-relaxed text-foreground">
            {task.description}
          </p>

          <SlotsIndicator accepted={task.accepted_count} teamSize={task.team_size} />

          <div className="space-y-2">
            <p className="text-sm font-medium text-foreground">Required skills</p>
            {task.skills.length > 0 ? (
              <div className="flex flex-wrap gap-1.5">
                {task.skills.map((skill) => (
                  <Badge key={skill} variant="secondary">
                    {skill}
                  </Badge>
                ))}
              </div>
            ) : (
              <p className="text-sm text-muted-foreground">No specific skills listed.</p>
            )}
          </div>

          <p className="text-xs text-muted-foreground">
            Posted {formatPostedDate(task.created_at)}
          </p>
        </CardContent>
      </Card>

      <TeamRoster
        members={team?.members ?? []}
        teamSize={team?.meta.team_size ?? task.team_size}
        openSlots={team?.meta.open_slots ?? task.open_slots}
        loading={teamLoading}
        error={teamError}
        onRetry={() => void loadTeam()}
      />

      {task.uploader ? (
        <Card>
          <CardHeader>
            <CardTitle>Posted by</CardTitle>
          </CardHeader>
          <CardContent>
            <Link
              to={`/users/${task.uploader.id}`}
              className="flex items-center gap-3 rounded-lg transition-colors hover:text-primary"
            >
              <Avatar className="size-11">
                {task.uploader.avatar ? (
                  <AvatarImage src={task.uploader.avatar} alt={task.uploader.name} />
                ) : null}
                <AvatarFallback className="bg-secondary text-sm font-semibold text-secondary-foreground">
                  {getInitials(task.uploader.name)}
                </AvatarFallback>
              </Avatar>
              <div className="min-w-0">
                <p className="truncate font-medium text-foreground">
                  {task.uploader.name}
                </p>
                <p className="flex items-center gap-1 text-sm text-muted-foreground">
                  <Star className="size-3.5 fill-amber-400 text-amber-400" />
                  {rating ? `${rating} rating` : "No ratings yet"}
                </p>
              </div>
            </Link>
          </CardContent>
        </Card>
      ) : null}
    </div>
  )
}
