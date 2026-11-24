import { useEffect, useMemo, useState } from "react"
import { Plus, SearchX, XCircle } from "lucide-react"
import { Link, useSearchParams } from "react-router-dom"
import { toast } from "sonner"

import { getApiErrorMessage } from "@/api/client"
import { EmptyState, ErrorState } from "@/components/PageStates"
import { Pagination } from "@/components/tasks/Pagination"
import { TaskCard } from "@/components/tasks/TaskCard"
import { TaskCardSkeleton } from "@/components/tasks/TaskCardSkeleton"
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
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { useTaskStore } from "@/stores/useTaskStore"
import type { TaskFilters, TaskStatus } from "@/types"

const TAB_STATUSES: Array<{ value: string; label: string; status?: TaskStatus }> = [
  { value: "all", label: "All" },
  { value: "open", label: "Open", status: "open" },
  { value: "full", label: "Full", status: "full" },
  { value: "in_progress", label: "In progress", status: "in_progress" },
  { value: "completed", label: "Completed", status: "completed" },
  { value: "cancelled", label: "Cancelled", status: "cancelled" },
]

const PER_PAGE = 12

function pageParam(value: string | null): number {
  const parsed = Number(value)

  return Number.isFinite(parsed) && parsed > 0 ? Math.floor(parsed) : 1
}

export function MyTasksPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const tasks = useTaskStore((state) => state.tasks)
  const meta = useTaskStore((state) => state.meta)
  const loading = useTaskStore((state) => state.loading)
  const error = useTaskStore((state) => state.error)
  const fetchMyTasks = useTaskStore((state) => state.fetchMyTasks)
  const cancelTask = useTaskStore((state) => state.cancelTask)
  const [busyId, setBusyId] = useState<number | null>(null)

  const statusValue = searchParams.get("status") ?? "all"
  const page = pageParam(searchParams.get("page"))
  const activeTab =
    TAB_STATUSES.find((tab) => tab.value === statusValue) ?? TAB_STATUSES[0]

  const filters = useMemo<TaskFilters>(
    () => ({
      status: activeTab.status,
      page,
      per_page: PER_PAGE,
    }),
    [activeTab.status, page]
  )

  useEffect(() => {
    void fetchMyTasks(filters)
  }, [fetchMyTasks, filters])

  function setStatus(value: string) {
    const next = new URLSearchParams()

    if (value !== "all") {
      next.set("status", value)
    }

    setSearchParams(next)
  }

  function setPage(nextPage: number) {
    const next = new URLSearchParams(searchParams)

    if (nextPage <= 1) {
      next.delete("page")
    } else {
      next.set("page", String(nextPage))
    }

    setSearchParams(next)
  }

  async function handleCancel(id: number) {
    setBusyId(id)

    try {
      await cancelTask(id)
      toast.success("Task cancelled.")
      await fetchMyTasks(filters)
    } catch (caught) {
      toast.error(getApiErrorMessage(caught))
    } finally {
      setBusyId(null)
    }
  }

  const showSkeletons = loading && tasks.length === 0

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="space-y-1">
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">
            My tasks
          </h1>
          <p className="text-sm text-muted-foreground">
            Manage the tasks you have posted.
          </p>
        </div>
        <Button asChild>
          <Link to="/tasks/new">
            <Plus className="size-4" />
            Post a task
          </Link>
        </Button>
      </div>

      <Tabs value={activeTab.value} onValueChange={setStatus}>
        <TabsList className="w-full justify-start overflow-x-auto">
          {TAB_STATUSES.map((tab) => (
            <TabsTrigger key={tab.value} value={tab.value}>
              {tab.label}
            </TabsTrigger>
          ))}
        </TabsList>
      </Tabs>

      {error ? (
        <ErrorState
          title="Could not load your tasks"
          message={error}
          onRetry={() => void fetchMyTasks(filters)}
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
          title={
            activeTab.status ? `No ${activeTab.label.toLowerCase()} tasks` : "No tasks yet"
          }
          description="Post a task to start finding teammates."
          action={
            <Button asChild>
              <Link to="/tasks/new">
                <Plus className="size-4" />
                Post a task
              </Link>
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
            {tasks.map((task) => {
              const canEdit = task.status === "open" || task.status === "full"
              const canCancel = canEdit

              return (
                <TaskCard
                  key={task.id}
                  task={task}
                  showApplicants
                  actions={
                    <>
                      <Button variant="outline" size="sm" asChild>
                        <Link to={`/tasks/${task.id}`}>View</Link>
                      </Button>
                      {canEdit ? (
                        <Button variant="outline" size="sm" asChild>
                          <Link to={`/tasks/${task.id}/edit`}>Edit</Link>
                        </Button>
                      ) : null}
                      {canCancel ? (
                        <AlertDialog>
                          <AlertDialogTrigger asChild>
                            <Button
                              variant="ghost"
                              size="sm"
                              disabled={busyId === task.id}
                            >
                              <XCircle className="size-4" />
                              Cancel
                            </Button>
                          </AlertDialogTrigger>
                          <AlertDialogContent>
                            <AlertDialogHeader>
                              <AlertDialogTitle>Cancel this task?</AlertDialogTitle>
                              <AlertDialogDescription>
                                Pending applications will be rejected. This cannot be
                                undone.
                              </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                              <AlertDialogCancel>Keep task</AlertDialogCancel>
                              <AlertDialogAction
                                onClick={() => {
                                  void handleCancel(task.id)
                                }}
                              >
                                Cancel task
                              </AlertDialogAction>
                            </AlertDialogFooter>
                          </AlertDialogContent>
                        </AlertDialog>
                      ) : null}
                    </>
                  }
                />
              )
            })}
          </div>

          {meta && meta.last_page > 1 ? (
            <Pagination
              page={meta.current_page}
              lastPage={meta.last_page}
              onPageChange={setPage}
              disabled={loading}
            />
          ) : null}
        </>
      )}
    </div>
  )
}
