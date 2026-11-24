import { useCallback, useEffect, useState } from "react"
import axios from "axios"
import { ArrowLeft, CalendarDays, Pencil, Star, Trash2, Users, XCircle } from "lucide-react"
import { Link, useNavigate, useParams } from "react-router-dom"
import { toast } from "sonner"

import { getApiErrorMessage } from "@/api/client"
import { getTask } from "@/api/tasks"
import { ErrorState } from "@/components/PageStates"
import { StatusBadge } from "@/components/StatusBadge"
import { SlotsIndicator } from "@/components/tasks/SlotsIndicator"
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
import { useTaskStore } from "@/stores/useTaskStore"
import type { Task } from "@/types"

function TaskDetailSkeleton() {
  return (
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

export function TaskDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const cancelTask = useTaskStore((state) => state.cancelTask)
  const removeTask = useTaskStore((state) => state.removeTask)
  const [task, setTask] = useState<Task | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notFound, setNotFound] = useState(false)
  const [busy, setBusy] = useState(false)

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

  useEffect(() => {
    void loadTask()
  }, [loadTask])

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
          {task.my_application_status ? (
            <StatusBadge status={task.my_application_status} />
          ) : null}
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

      {isOwner ? (
        <div className="flex flex-wrap gap-2">
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
      ) : (
        <div className="flex flex-col gap-1.5 sm:items-start">
          <Button disabled className="sm:w-auto">
            Apply to this task
          </Button>
          <p className="text-xs text-muted-foreground">
            Applications open on Day 3.
          </p>
        </div>
      )}

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
