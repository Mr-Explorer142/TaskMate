import { usePageTitle } from "@/hooks/usePageTitle"
import { useCallback, useEffect, useState } from "react"
import axios from "axios"
import { ArrowLeft, CalendarDays, CheckCircle2, Info, Pencil, PlayCircle, Star, Trash2, Users, XCircle } from "lucide-react"
import { Link, useNavigate, useParams } from "react-router-dom"
import { toast } from "sonner"

import { getApiErrorMessage } from "@/api/client"
import { getTask, getTaskTeam } from "@/api/tasks"
import { ApplicationStatusBadge } from "@/components/applications/ApplicationStatusBadge"
import { ApplyDialog } from "@/components/applications/ApplyDialog"
import { MessageButton } from "@/components/chat/MessageButton"
import { ErrorState } from "@/components/PageStates"
import { PendingReviews } from "@/components/reviews/PendingReviews"
import { StatusBadge } from "@/components/StatusBadge"
import { CompleteProjectDialog } from "@/components/tasks/CompleteProjectDialog"
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
import {
  formatDeadline,
  formatPostedDate,
  formatRatingLabel,
  formatReward,
} from "@/lib/format"
import { loginUrl, registerUrl } from "@/lib/redirect"
import { getInitials } from "@/lib/utils"
import { useApplicationStore } from "@/stores/useApplicationStore"
import { useAuthStore } from "@/stores/useAuthStore"
import { usePointsStore } from "@/stores/usePointsStore"
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
  usePageTitle("Task details")
  const { id } = useParams()
  const navigate = useNavigate()
  const isGuest = !useAuthStore((state) => state.token)
  const cancelTask = useTaskStore((state) => state.cancelTask)
  const completeTask = useTaskStore((state) => state.completeTask)
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
      void usePointsStore.getState().fetchSummary()
    } catch (caught) {
      toast.error(getApiErrorMessage(caught))
    } finally {
      setBusy(false)
    }
  }

  async function handleComplete() {
    if (!task) {
      return
    }

    setBusy(true)

    try {
      const updated = await completeTask(task.id)
      setTask(updated)
      toast.success("Project completed.")
      void usePointsStore.getState().fetchSummary()
    } catch (caught) {
      toast.error(getApiErrorMessage(caught))
    } finally {
      setBusy(false)
    }
  }

  function handleReviewed() {
    setTask((current) =>
      current
        ? {
            ...current,
            pending_reviews_count: Math.max(
              0,
              (current.pending_reviews_count ?? 0) - 1
            ),
          }
        : current
    )
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
      toast.success("Application sent.")
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
  const isCompleted = task.status === "completed"
  const canEdit = isOwner && (task.status === "open" || task.status === "full")
  const canCancel =
    isOwner &&
    (task.status === "open" || task.status === "full" || isInProgress)
  const canDelete = isOwner && (task.status === "open" || task.status === "cancelled")
  const taskPath = `/tasks/${task.id}`
  const rating = task.uploader
    ? formatRatingLabel(
        task.uploader.average_rating,
        task.uploader.reviews_count
      )
    : null
  const uploaderSummary = task.uploader ? (
    <>
      <Avatar className="size-11">
        {task.uploader.avatar_url ? (
          <AvatarImage src={task.uploader.avatar_url} alt={task.uploader.name} />
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
          <Star className="size-3.5 fill-primary text-primary" />
          {rating}
        </p>
      </div>
    </>
  ) : null

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

      {task.type === "points" ? (
        <div className="flex items-start gap-3 rounded-lg border border-border bg-muted/30 p-3 text-sm text-muted-foreground">
          <Info className="mt-0.5 size-4 shrink-0 text-primary" />
          <p>
            Reward is per member. When you start the project, reward x accepted
            members will be held in escrow.
          </p>
        </div>
      ) : null}

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

      {isCompleted ? (
        <div className="flex items-start gap-3 rounded-lg border border-status-open/20 bg-status-open-bg p-4 text-status-open">
          <CheckCircle2 className="mt-0.5 size-5 shrink-0" />
          <div className="space-y-0.5">
            <p className="text-sm font-semibold">Project completed</p>
            <p className="text-sm">
              {task.completed_at
                ? `Finished on ${formatPostedDate(task.completed_at)}.`
                : "The team is now closed."}
              {task.is_participant && (task.pending_reviews_count ?? 0) > 0
                ? ` ${task.pending_reviews_count} review${task.pending_reviews_count === 1 ? "" : "s"} left to submit.`
                : ""}
            </p>
          </div>
        </div>
      ) : null}

      {isGuest ? (
        <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
          <Button asChild size="sm">
            <Link to={loginUrl(taskPath)}>Log in to apply</Link>
          </Button>
          <p className="text-sm text-muted-foreground">
            New here?{" "}
            <Link
              to={registerUrl(taskPath)}
              className="font-medium text-primary hover:underline"
            >
              Sign up
            </Link>
          </p>
        </div>
      ) : isOwner ? (
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

          {task.can_complete ? (
            <CompleteProjectDialog
              task={task}
              busy={busy}
              onConfirm={() => void handleComplete()}
              onCancel={() => void handleCancel()}
            />
          ) : null}

          {canCancel && !task.can_complete ? (
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
                    {isInProgress
                      ? "Escrowed points will be refunded to you and members get nothing. This cannot be undone."
                      : "Pending applications will be rejected. This cannot be undone."}
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
          <div className="flex flex-wrap items-center gap-2">
            <ApplicationStatusBadge
              status="accepted"
              label="You are on this team"
            />
            {task.uploader ? (
              <MessageButton
                taskId={task.id}
                userId={task.uploader.id}
                label="Message uploader"
              />
            ) : null}
          </div>
        ) : null
      ) : isCompleted && task.is_participant ? (
        <div className="flex flex-wrap items-center gap-2">
          <ApplicationStatusBadge
            status="accepted"
            label="You were on this team"
          />
          {task.uploader ? (
            <MessageButton
              taskId={task.id}
              userId={task.uploader.id}
              label="Message uploader"
            />
          ) : null}
          {(task.pending_reviews_count ?? 0) > 0 ? (
            <span className="text-xs text-muted-foreground">
              {task.pending_reviews_count} review
              {task.pending_reviews_count === 1 ? "" : "s"} left to submit below.
            </span>
          ) : null}
        </div>
      ) : isCompleted ? (
        <div className="flex flex-wrap items-center gap-2">
          <StatusBadge status="completed" />
        </div>
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
                  Keep application
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
          {task.uploader ? (
            <MessageButton
              taskId={task.id}
              userId={task.uploader.id}
              label="Message uploader"
            />
          ) : null}
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
          {task.uploader ? (
            <MessageButton
              taskId={task.id}
              userId={task.uploader.id}
              label="Message uploader"
            />
          ) : null}
        </div>
      ) : task.my_application_status === "rejected" ? (
        <ApplicationStatusBadge status="rejected" label="Not selected" />
      ) : task.my_application_status === "left" ? (
        <ApplicationStatusBadge status="left" />
      ) : task.status === "open" ? (
        <div className="flex flex-col gap-1.5 sm:items-start">
          <div className="flex flex-wrap gap-2">
            <Button
              onClick={() => setApplyOpen(true)}
              disabled={applicationBusy}
              className="sm:w-auto"
            >
              Apply to this task
            </Button>
            {task.uploader ? (
              <MessageButton
                taskId={task.id}
                userId={task.uploader.id}
                label="Message uploader"
              />
            ) : null}
          </div>
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

      {!isGuest ? (
        <ApplyDialog
          open={applyOpen}
          onOpenChange={setApplyOpen}
          taskTitle={task.title}
          submitting={applicationBusy}
          onSubmit={(message) => void handleApply(message)}
        />
      ) : null}

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

      {isCompleted && task.is_participant ? (
        <PendingReviews taskId={task.id} onReviewed={handleReviewed} />
      ) : null}

      {uploaderSummary ? (
        <Card>
          <CardHeader>
            <CardTitle>Posted by</CardTitle>
          </CardHeader>
          <CardContent>
            {isGuest ? (
              <div className="flex items-center gap-3">{uploaderSummary}</div>
            ) : (
              <Link
                to={`/users/${task.uploader?.id}`}
                className="flex items-center gap-3 rounded-lg transition-colors hover:text-primary"
              >
                {uploaderSummary}
              </Link>
            )}
          </CardContent>
        </Card>
      ) : null}
    </div>
  )
}
