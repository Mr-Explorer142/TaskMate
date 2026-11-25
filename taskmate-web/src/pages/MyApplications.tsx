import { useEffect, useMemo, useState } from "react"
import { SearchX } from "lucide-react"
import { Link, useSearchParams } from "react-router-dom"
import { toast } from "sonner"

import type { ApplicationListFilters } from "@/api/applications"
import { getApiErrorMessage } from "@/api/client"
import { ApplicationRow } from "@/components/applications/ApplicationRow"
import { EmptyState, ErrorState } from "@/components/PageStates"
import { Pagination } from "@/components/tasks/Pagination"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { useApplicationStore } from "@/stores/useApplicationStore"
import type { ApplicationStatus } from "@/types"

interface MyApplicationTab {
  value: string
  label: string
  status?: ApplicationStatus
}

const TABS: MyApplicationTab[] = [
  { value: "all", label: "All" },
  { value: "pending", label: "Pending", status: "pending" },
  { value: "accepted", label: "Accepted", status: "accepted" },
  { value: "rejected", label: "Rejected", status: "rejected" },
  { value: "left", label: "Left", status: "left" },
]

function pageParam(value: string | null): number {
  const parsed = Number(value)

  return Number.isFinite(parsed) && parsed > 0 ? Math.floor(parsed) : 1
}

export function MyApplicationsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const applications = useApplicationStore((state) => state.myApplications)
  const meta = useApplicationStore((state) => state.myMeta)
  const loading = useApplicationStore((state) => state.myLoading)
  const error = useApplicationStore((state) => state.myError)
  const fetchApplications = useApplicationStore(
    (state) => state.fetchMyApplications
  )
  const withdraw = useApplicationStore((state) => state.withdraw)
  const leave = useApplicationStore((state) => state.leave)
  const [busyId, setBusyId] = useState<number | null>(null)

  const statusValue = searchParams.get("status") ?? "all"
  const page = pageParam(searchParams.get("page"))
  const activeTab = TABS.find((tab) => tab.value === statusValue) ?? TABS[0]

  const filters = useMemo<ApplicationListFilters>(
    () => ({ status: activeTab.status, page }),
    [activeTab.status, page]
  )

  useEffect(() => {
    void fetchApplications(filters)
  }, [fetchApplications, filters])

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

  async function handleWithdraw(applicationId: number) {
    setBusyId(applicationId)

    try {
      await withdraw(applicationId)
      toast.success("Application withdrawn.")

      if (applications.length === 1 && page > 1) {
        setPage(page - 1)
      } else {
        await fetchApplications(filters)
      }
    } catch (caught) {
      toast.error(getApiErrorMessage(caught))
    } finally {
      setBusyId(null)
    }
  }

  async function handleLeave(applicationId: number) {
    setBusyId(applicationId)

    try {
      await leave(applicationId)
      toast.success("You have left the team.")

      if (applications.length === 1 && page > 1) {
        setPage(page - 1)
      } else {
        await fetchApplications(filters)
      }
    } catch (caught) {
      toast.error(getApiErrorMessage(caught))
    } finally {
      setBusyId(null)
    }
  }

  const showSkeletons = loading && applications.length === 0

  return (
    <div className="space-y-6">
      <div className="space-y-1">
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">
          My applications
        </h1>
        <p className="text-sm text-muted-foreground">
          Track the tasks you have applied to and their status.
        </p>
      </div>

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
          title="Could not load your applications"
          message={error}
          onRetry={() => void fetchApplications(filters)}
        />
      ) : showSkeletons ? (
        <div className="space-y-4">
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={index} className="h-28 w-full rounded-lg" />
          ))}
        </div>
      ) : applications.length === 0 ? (
        <EmptyState
          icon={SearchX}
          title={
            activeTab.status
              ? `No ${activeTab.label.toLowerCase()} applications`
              : "No applications yet"
          }
          description="Find a task that matches your skills and send your first application."
          action={
            <Button asChild>
              <Link to="/tasks">Browse tasks</Link>
            </Button>
          }
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

          <div className="space-y-3">
            {applications.map((application) => (
              <ApplicationRow
                key={application.id}
                application={application}
                busy={busyId !== null}
                onWithdraw={(applicationId) => void handleWithdraw(applicationId)}
                onLeave={(applicationId) => void handleLeave(applicationId)}
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
