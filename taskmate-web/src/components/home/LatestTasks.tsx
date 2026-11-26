import { useCallback, useEffect, useState } from "react"
import { Link } from "react-router-dom"

import { listTasks } from "@/api/tasks"
import { ErrorState } from "@/components/PageStates"
import { TaskCard } from "@/components/tasks/TaskCard"
import { TaskCardSkeleton } from "@/components/tasks/TaskCardSkeleton"
import { getApiErrorMessage } from "@/api/client"
import type { Task } from "@/types"

export function LatestTasks() {
  const [tasks, setTasks] = useState<Task[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [reloadKey, setReloadKey] = useState(0)

  const load = useCallback(async (signal: AbortSignal) => {
    try {
      const result = await listTasks(
        {
          status: "open",
          sort: "-created_at",
          per_page: 6,
        },
        signal
      )

      if (!signal.aborted) {
        setTasks(result.data)
      }
    } catch (caught) {
      if (!signal.aborted) {
        setError(getApiErrorMessage(caught))
      }
    } finally {
      if (!signal.aborted) {
        setLoading(false)
      }
    }
  }, [])

  useEffect(() => {
    const controller = new AbortController()

    void load(controller.signal)

    return () => controller.abort()
  }, [load, reloadKey])

  function handleRetry() {
    setError(null)
    setLoading(true)
    setReloadKey((key) => key + 1)
  }

  if (!loading && !error && tasks.length === 0) {
    return null
  }

  return (
    <section className="mx-auto w-full max-w-6xl px-4 py-16 sm:py-20">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <h2 className="text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
          Latest open tasks
        </h2>
        <Link
          to="/tasks"
          className="rounded-lg text-sm font-medium text-primary hover:underline focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
        >
          View all tasks
        </Link>
      </div>

      <div className="mt-8">
        {error ? (
          <ErrorState
            title="Could not load tasks"
            message={error}
            onRetry={handleRetry}
          />
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {loading
              ? Array.from({ length: 6 }).map((_, index) => (
                  <TaskCardSkeleton key={index} />
                ))
              : tasks.map((task) => <TaskCard key={task.id} task={task} />)}
          </div>
        )}
      </div>
    </section>
  )
}
