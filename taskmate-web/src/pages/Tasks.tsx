import { useEffect, useMemo } from "react"
import { SearchX } from "lucide-react"
import { useSearchParams } from "react-router-dom"

import { EmptyState, ErrorState } from "@/components/PageStates"
import { Pagination } from "@/components/tasks/Pagination"
import { TaskCard } from "@/components/tasks/TaskCard"
import { TaskCardSkeleton } from "@/components/tasks/TaskCardSkeleton"
import { TaskFilters } from "@/components/tasks/TaskFilters"
import { Button } from "@/components/ui/button"
import { useTaskStore } from "@/stores/useTaskStore"
import type { TaskFilters as TaskFiltersValue, TaskSort, TaskType } from "@/types"

const TYPES: TaskType[] = ["paid", "points"]
const SORTS: TaskSort[] = ["-created_at", "-reward", "deadline", "team_size"]

function numberParam(value: string | null): number | undefined {
  if (value === null || value.trim() === "") {
    return undefined
  }

  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : undefined
}

function parseFilters(params: URLSearchParams): TaskFiltersValue {
  const type = params.get("type") as TaskType | null
  const sort = params.get("sort") as TaskSort | null
  const page = numberParam(params.get("page"))
  const hasOpenSlots = params.get("has_open_slots")

  return {
    search: params.get("search")?.trim() || undefined,
    type: type && TYPES.includes(type) ? type : undefined,
    skill: params.get("skill")?.trim() || undefined,
    min_reward: numberParam(params.get("min_reward")),
    max_reward: numberParam(params.get("max_reward")),
    has_open_slots:
      hasOpenSlots === "1" || hasOpenSlots === "true" ? true : undefined,
    sort: sort && SORTS.includes(sort) ? sort : undefined,
    page: page && page > 0 ? Math.floor(page) : 1,
    per_page: 12,
  }
}

export function TasksPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const tasks = useTaskStore((state) => state.tasks)
  const meta = useTaskStore((state) => state.meta)
  const loading = useTaskStore((state) => state.loading)
  const error = useTaskStore((state) => state.error)
  const fetchTasks = useTaskStore((state) => state.fetchTasks)

  const query = searchParams.toString()
  const filters = useMemo(
    () => parseFilters(new URLSearchParams(query)),
    [query]
  )

  useEffect(() => {
    void fetchTasks(parseFilters(new URLSearchParams(query)))
  }, [query, fetchTasks])

  function updateFilters(patch: Partial<TaskFiltersValue>) {
    const next = new URLSearchParams(searchParams)

    for (const [key, value] of Object.entries(patch)) {
      if (
        value === undefined ||
        value === null ||
        value === "" ||
        value === false ||
        (key === "sort" && value === "-created_at") ||
        (key === "page" && value === 1)
      ) {
        next.delete(key)
      } else if (key === "has_open_slots" && value === true) {
        next.set(key, "1")
      } else {
        next.set(key, String(value))
      }
    }

    if (!("page" in patch)) {
      next.delete("page")
    }

    setSearchParams(next)
  }

  function clearFilters() {
    setSearchParams(new URLSearchParams())
  }

  function goToPage(page: number) {
    updateFilters({ page })
  }

  const showSkeletons = loading && tasks.length === 0

  return (
    <div className="space-y-6">
      <div className="space-y-1">
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">
          Browse tasks
        </h1>
        <p className="text-sm text-muted-foreground">
          Find a team, contribute your skills, and get the work done.
        </p>
      </div>

      <TaskFilters
        filters={filters}
        onChange={updateFilters}
        onClear={clearFilters}
      />

      {error ? (
        <ErrorState
          title="Could not load tasks"
          message={error}
          onRetry={() => void fetchTasks(filters)}
        />
      ) : showSkeletons ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {Array.from({ length: 6 }).map((_, index) => (
            <TaskCardSkeleton key={index} />
          ))}
        </div>
      ) : tasks.length === 0 ? (
        <EmptyState
          icon={SearchX}
          title="No tasks found"
          description="Try adjusting your search or filters to see more tasks."
          action={
            <Button variant="outline" onClick={clearFilters}>
              Clear filters
            </Button>
          }
        />
      ) : (
        <>
          <div className="flex items-center justify-between gap-3">
            <p className="text-sm text-muted-foreground">
              {meta?.total ?? tasks.length}{" "}
              {(meta?.total ?? tasks.length) === 1 ? "task" : "tasks"}
            </p>
            {loading ? (
              <span className="text-xs text-muted-foreground">Updating...</span>
            ) : null}
          </div>

          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {tasks.map((task) => (
              <TaskCard key={task.id} task={task} />
            ))}
          </div>

          {meta && meta.last_page > 1 ? (
            <Pagination
              page={meta.current_page}
              lastPage={meta.last_page}
              onPageChange={goToPage}
              disabled={loading}
            />
          ) : null}
        </>
      )}
    </div>
  )
}
