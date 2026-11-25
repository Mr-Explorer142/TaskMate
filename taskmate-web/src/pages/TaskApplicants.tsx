import { usePageTitle } from "@/hooks/usePageTitle"
import { useCallback, useEffect, useMemo, useState } from "react"
import axios from "axios"
import { ArrowLeft, Inbox, Info, PlayCircle, SearchX } from "lucide-react"
import { Link, useNavigate, useParams, useSearchParams } from "react-router-dom"
import { toast } from "sonner"

import type { ApplicationListFilters } from "@/api/applications"
import { getApiErrorMessage } from "@/api/client"
import { getTask, getTaskTeam, startTask } from "@/api/tasks"
import { ApplicantCard } from "@/components/applications/ApplicantCard"
import { EmptyState, ErrorState } from "@/components/PageStates"
import { StatusBadge } from "@/components/StatusBadge"
import { Pagination } from "@/components/tasks/Pagination"
import { SlotsIndicator } from "@/components/tasks/SlotsIndicator"
import { StartProjectDialog } from "@/components/tasks/StartProjectDialog"
import { TeamRoster } from "@/components/tasks/TeamRoster"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Skeleton } from "@/components/ui/skeleton"
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { useApplicationStore } from "@/stores/useApplicationStore"
import { usePointsStore } from "@/stores/usePointsStore"
import type { ApplicationStatus, Task, Team } from "@/types"

interface ApplicantTab {
  value: string
  label: string
  status?: ApplicationStatus
}

const TABS: ApplicantTab[] = [
  { value: "pending", label: "Pending", status: "pending" },
  { value: "accepted", label: "Accepted", status: "accepted" },
  { value: "rejected", label: "Rejected", status: "rejected" },
  { value: "all", label: "All" },
]

function pageParam(value: string | null): number {
  const parsed = Number(value)

  return Number.isFinite(parsed) && parsed > 0 ? Math.floor(parsed) : 1
}

function ApplicantsSkeleton() {
  return (
    <div className="mx-auto max-w-4xl space-y-6">
      <Skeleton className="h-7 w-2/3" />
      <Skeleton className="h-4 w-40" />
      <Skeleton className="h-2 w-full rounded-full" />
      <div className="space-y-4">
        {Array.from({ length: 3 }).map((_, index) => (
          <Skeleton key={index} className="h-48 w-full rounded-lg" />
        ))}
      </div>
    </div>
  )
}

export function TaskApplicantsPage() {
  usePageTitle("Applicants")
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()
  const taskId = Number(id)

  const applications = useApplicationStore((state) => state.taskApplications)
  const meta = useApplicationStore((state) => state.taskMeta)
  const loading = useApplicationStore((state) => state.taskLoading)
  const error = useApplicationStore((state) => state.taskError)
  const fetchApplications = useApplicationStore(
    (state) => state.fetchTaskApplications
  )
  const accept = useApplicationStore((state) => state.accept)
  const reject = useApplicationStore((state) => state.reject)
  const resetApplications = useApplicationStore(
    (state) => state.resetTaskApplications
  )

  const [task, setTask] = useState<Task | null>(null)
  const [team, setTeam] = useState<Team | null>(null)
  const [taskLoading, setTaskLoading] = useState(true)
  const [teamLoading, setTeamLoading] = useState(true)
  const [taskError, setTaskError] = useState<string | null>(null)
  const [teamError, setTeamError] = useState<string | null>(null)
  const [notFound, setNotFound] = useState(false)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [startBusy, setStartBusy] = useState(false)

  const statusValue = searchParams.get("status") ?? "pending"
  const page = pageParam(searchParams.get("page"))
  const activeTab = TABS.find((tab) => tab.value === statusValue) ?? TABS[0]

  const filters = useMemo<ApplicationListFilters>(
    () => ({ status: activeTab.status, page }),
    [activeTab.status, page]
  )

  const loadTask = useCallback(async () => {
    if (!Number.isFinite(taskId) || taskId <= 0) {
      setNotFound(true)
      setTaskLoading(false)
      return
    }

    setTaskLoading(true)
    setTaskError(null)
    setNotFound(false)

    try {
      setTask(await getTask(taskId))
    } catch (caught) {
      if (axios.isAxiosError(caught) && caught.response?.status === 404) {
        setNotFound(true)
      } else {
        setTaskError(getApiErrorMessage(caught))
      }
    } finally {
      setTaskLoading(false)
    }
  }, [taskId])

  const loadTeam = useCallback(async () => {
    if (!Number.isFinite(taskId) || taskId <= 0) {
      setTeamLoading(false)
      return
    }

    setTeamLoading(true)
    setTeamError(null)

    try {
      setTeam(await getTaskTeam(taskId))
    } catch (caught) {
      setTeamError(getApiErrorMessage(caught))
    } finally {
      setTeamLoading(false)
    }
  }, [taskId])

  useEffect(() => {
    void loadTask()
  }, [loadTask])

  useEffect(() => {
    void loadTeam()
  }, [loadTeam])

  useEffect(() => {
    if (task && !task.is_owner) {
      toast.error("Only the task owner can view applicants.")
      navigate(`/tasks/${task.id}`, { replace: true })
    }
  }, [task, navigate])

  const listTaskId = task?.is_owner ? task.id : null

  useEffect(() => {
    if (listTaskId === null) {
      return
    }

    void fetchApplications(listTaskId, filters)
  }, [listTaskId, filters, fetchApplications])

  useEffect(() => {
    return () => resetApplications()
  }, [resetApplications])

  function setStatus(value: string) {
    const next = new URLSearchParams()

    if (value !== "pending") {
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

  async function refreshTask(activeTaskId: number): Promise<Task | null> {
    try {
      const refreshed = await getTask(activeTaskId)
      setTask(refreshed)
      return refreshed
    } catch {
      return null
    }
  }

  async function handleAccept(applicationId: number) {
    if (!task) {
      return
    }

    const activeTaskId = task.id
    setBusyId(applicationId)

    try {
      await accept(applicationId)
      const refreshed = await refreshTask(activeTaskId)
      await fetchApplications(activeTaskId, filters)
      await loadTeam()

      toast.success(
        refreshed?.status === "full" ? "Team is full" : "Application accepted."
      )
    } catch (caught) {
      toast.error(getApiErrorMessage(caught))
      await refreshTask(activeTaskId)
      await fetchApplications(activeTaskId, filters)
    } finally {
      setBusyId(null)
    }
  }

  async function handleReject(applicationId: number) {
    if (!task) {
      return
    }

    const activeTaskId = task.id
    setBusyId(applicationId)

    try {
      await reject(applicationId)
      toast.success("Application rejected.")
    } catch (caught) {
      toast.error(getApiErrorMessage(caught))
    } finally {
      setBusyId(null)
      await refreshTask(activeTaskId)
      await fetchApplications(activeTaskId, filters)
    }
  }

  async function handleStart() {
    if (!task) {
      return
    }

    const activeTaskId = task.id
    setStartBusy(true)

    try {
      const updated = await startTask(activeTaskId)
      setTask(updated)
      toast.success("Project started.")
      if (updated.type === "points") {
        void usePointsStore.getState().fetchSummary()
      }
      await fetchApplications(activeTaskId, filters)
      await loadTeam()
    } catch (caught) {
      toast.error(getApiErrorMessage(caught))
    } finally {
      setStartBusy(false)
    }
  }

  if (taskLoading) {
    return <ApplicantsSkeleton />
  }

  if (notFound) {
    return (
      <Card>
        <CardContent className="flex flex-col items-center gap-4 py-12 text-center">
          <div className="space-y-1">
            <h1 className="text-xl font-semibold text-foreground">
              Task not found
            </h1>
            <p className="text-sm text-muted-foreground">
              This task may have been deleted or never existed.
            </p>
          </div>
          <Button variant="outline" asChild>
            <Link to="/tasks">
              <ArrowLeft className="size-4" />
              Back to tasks
            </Link>
          </Button>
        </CardContent>
      </Card>
    )
  }

  if (taskError || !task) {
    return (
      <ErrorState
        title="Could not load applicants"
        message={taskError ?? "Something went wrong."}
        onRetry={() => void loadTask()}
      />
    )
  }

  if (!task.is_owner) {
    return null
  }

  const teamFull = task.status === "full" || task.accepted_count >= task.team_size
  const isInProgress = task.status === "in_progress"
  const showPendingHint =
    task.status === "open" &&
    (team?.meta.open_slots ?? task.open_slots) > 0 &&
    (task.pending_count ?? 0) > 0
  const showSkeletons = loading && applications.length === 0
  const emptyTitle =
    activeTab.status === undefined
      ? "No applicants yet"
      : `No ${activeTab.label.toLowerCase()} applicants yet`
  const emptyDescription =
    activeTab.status === "pending"
      ? "New applications will appear here."
      : "Try another tab to see more applications."

  return (
    <div className="mx-auto max-w-4xl space-y-6">
      <Button variant="ghost" size="sm" asChild className="-ml-2">
        <Link to={`/tasks/${task.id}`}>
          <ArrowLeft className="size-4" />
          Back to task
        </Link>
      </Button>

      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div className="space-y-3">
          <div className="flex flex-wrap items-center gap-2">
            <StatusBadge status={task.status} />
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">
              {task.title}
            </h1>
          </div>
          <div className="max-w-sm">
            <SlotsIndicator
              accepted={task.accepted_count}
              teamSize={task.team_size}
            />
          </div>
        </div>

        {task.can_start ? (
          <StartProjectDialog
            task={task}
            busy={startBusy}
            onConfirm={() => void handleStart()}
          />
        ) : null}
      </div>

      {isInProgress ? (
        <div className="flex items-start gap-3 rounded-lg border border-status-in-progress/20 bg-status-in-progress-bg p-4 text-status-in-progress">
          <PlayCircle className="mt-0.5 size-5 shrink-0" />
          <div className="space-y-0.5">
            <p className="text-sm font-semibold">Project in progress</p>
            <p className="text-sm">
              The team is locked. New applications and team changes are closed.
            </p>
          </div>
        </div>
      ) : null}

      <TeamRoster
        members={team?.members ?? []}
        teamSize={team?.meta.team_size ?? task.team_size}
        openSlots={team?.meta.open_slots ?? task.open_slots}
        loading={teamLoading}
        error={teamError}
        onRetry={() => void loadTeam()}
      />

      {showPendingHint ? (
        <div className="flex items-center gap-2 rounded-lg border border-primary/20 bg-primary/5 p-3 text-sm text-foreground">
          <Info className="size-4 shrink-0 text-primary" />
          A slot is open. Accept someone from Pending.
        </div>
      ) : null}

      <Tabs value={activeTab.value} onValueChange={setStatus}>
        <TabsList className="w-full justify-start overflow-x-auto">
          {TABS.map((tab) => (
            <TabsTrigger key={tab.value} value={tab.value}>
              {tab.label}
            </TabsTrigger>
          ))}
        </TabsList>
      </Tabs>

      {error ? (
        <ErrorState
          title="Could not load applicants"
          message={error}
          onRetry={() => void fetchApplications(task.id, filters)}
        />
      ) : showSkeletons ? (
        <div className="space-y-4">
          {Array.from({ length: 3 }).map((_, index) => (
            <Skeleton key={index} className="h-48 w-full rounded-lg" />
          ))}
        </div>
      ) : applications.length === 0 ? (
        <EmptyState
          icon={activeTab.status === "pending" ? Inbox : SearchX}
          title={emptyTitle}
          description={emptyDescription}
        />
      ) : (
        <>
          <div className="flex items-center justify-between gap-3">
            <p className="text-sm text-muted-foreground">
              {meta?.total ?? applications.length}{" "}
              {(meta?.total ?? applications.length) === 1
                ? "application"
                : "applications"}
            </p>
            {loading ? (
              <span className="text-xs text-muted-foreground">Updating...</span>
            ) : null}
          </div>

          <div className="space-y-4">
            {applications.map((application) => (
              <ApplicantCard
                key={application.id}
                application={application}
                taskId={task.id}
                teamFull={teamFull}
                locked={isInProgress}
                busy={busyId !== null}
                onAccept={(applicationId) => void handleAccept(applicationId)}
                onReject={(applicationId) => void handleReject(applicationId)}
              />
            ))}
          </div>

          {meta && meta.last_page > 1 ? (
            <Pagination
              page={meta.current_page}
              lastPage={meta.last_page}
              onPageChange={setPage}
              disabled={loading || busyId !== null}
            />
          ) : null}
        </>
      )}
    </div>
  )
}
