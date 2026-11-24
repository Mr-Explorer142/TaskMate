import { useCallback, useEffect, useState } from "react"
import axios from "axios"
import { ArrowLeft } from "lucide-react"
import { Link, useNavigate, useParams } from "react-router-dom"
import { toast } from "sonner"

import { getApiErrorMessage } from "@/api/client"
import { getTask } from "@/api/tasks"
import { ErrorState } from "@/components/PageStates"
import { TaskForm } from "@/components/tasks/TaskForm"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Skeleton } from "@/components/ui/skeleton"
import type { Task } from "@/types"

function EditTaskSkeleton() {
  return (
    <div className="mx-auto max-w-3xl space-y-4">
      <Skeleton className="h-8 w-48" />
      <Skeleton className="h-4 w-64" />
      <Skeleton className="h-96 w-full rounded-lg" />
    </div>
  )
}

export function EditTaskPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [task, setTask] = useState<Task | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notFound, setNotFound] = useState(false)

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

      if (!result.is_owner) {
        toast.error("You can only edit tasks you posted.")
        navigate(`/tasks/${result.id}`, { replace: true })
        return
      }

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
  }, [id, navigate])

  useEffect(() => {
    void loadTask()
  }, [loadTask])

  if (loading) {
    return <EditTaskSkeleton />
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
            <Link to="/my-tasks">Back to my tasks</Link>
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

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <Button variant="ghost" size="sm" asChild className="-ml-2">
        <Link to={`/tasks/${task.id}`}>
          <ArrowLeft className="size-4" />
          Back to task
        </Link>
      </Button>

      <div className="space-y-1">
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">
          Edit task
        </h1>
        <p className="text-sm text-muted-foreground">
          Update the details of your posted task.
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Task details</CardTitle>
        </CardHeader>
        <CardContent>
          <TaskForm task={task} />
        </CardContent>
      </Card>
    </div>
  )
}
