import { usePageTitle } from "@/hooks/usePageTitle"
import { useEffect } from "react"
import { AlertCircle, Coins, Lock, Receipt } from "lucide-react"
import { useSearchParams } from "react-router-dom"

import { EmptyState, ErrorState } from "@/components/PageStates"
import { TransactionRow } from "@/components/points/TransactionRow"
import { Pagination } from "@/components/tasks/Pagination"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Skeleton } from "@/components/ui/skeleton"
import { formatPoints } from "@/lib/format"
import { usePointsStore } from "@/stores/usePointsStore"

function pageParam(value: string | null): number {
  const parsed = Number(value)

  return Number.isFinite(parsed) && parsed > 0 ? Math.floor(parsed) : 1
}

export function PointsPage() {
  usePageTitle("Points")
  const [searchParams, setSearchParams] = useSearchParams()
  const page = pageParam(searchParams.get("page"))

  const summary = usePointsStore((state) => state.summary)
  const summaryLoading = usePointsStore((state) => state.summaryLoading)
  const summaryError = usePointsStore((state) => state.summaryError)
  const transactions = usePointsStore((state) => state.transactions)
  const meta = usePointsStore((state) => state.meta)
  const transactionsLoading = usePointsStore(
    (state) => state.transactionsLoading
  )
  const transactionsError = usePointsStore((state) => state.transactionsError)
  const fetchSummary = usePointsStore((state) => state.fetchSummary)
  const fetchTransactions = usePointsStore((state) => state.fetchTransactions)

  useEffect(() => {
    void fetchSummary()
  }, [fetchSummary])

  useEffect(() => {
    void fetchTransactions(page)
  }, [fetchTransactions, page])

  function setPage(nextPage: number) {
    const next = new URLSearchParams(searchParams)

    if (nextPage <= 1) {
      next.delete("page")
    } else {
      next.set("page", String(nextPage))
    }

    setSearchParams(next)
  }

  const showTransactionsSkeleton = transactionsLoading && transactions.length === 0

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <div className="space-y-1">
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">
          Points
        </h1>
        <p className="text-sm text-muted-foreground">
          Your balance, escrow, and point history.
        </p>
      </div>

      {summaryError && summary ? (
        <div
          role="alert"
          className="flex flex-col gap-2 rounded-lg border border-status-pending/30 bg-status-pending-bg p-3 text-sm sm:flex-row sm:items-center sm:justify-between"
        >
          <p className="flex items-center gap-2 text-foreground">
            <AlertCircle className="size-4 shrink-0 text-status-pending" />
            {summaryError} Showing the last saved balance.
          </p>
          <Button
            variant="outline"
            size="sm"
            onClick={() => void fetchSummary()}
          >
            Try again
          </Button>
        </div>
      ) : null}

      {summaryError && !summary ? (
        <ErrorState
          title="Could not load your points"
          message={summaryError}
          onRetry={() => void fetchSummary()}
        />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-sm text-muted-foreground">
                <Coins className="size-4 text-primary" />
                Balance
              </CardTitle>
            </CardHeader>
            <CardContent>
              {summaryLoading && !summary ? (
                <Skeleton className="h-8 w-24" />
              ) : (
                <p className="text-3xl font-semibold tabular-nums text-foreground">
                  {formatPoints(summary?.balance ?? 0)}
                  <span className="ml-1 text-sm font-normal text-muted-foreground">
                    pts
                  </span>
                </p>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-sm text-muted-foreground">
                <Lock className="size-4 text-status-pending" />
                In escrow
              </CardTitle>
            </CardHeader>
            <CardContent>
              {summaryLoading && !summary ? (
                <Skeleton className="h-8 w-24" />
              ) : (
                <p className="text-3xl font-semibold tabular-nums text-foreground">
                  {formatPoints(summary?.escrowed ?? 0)}
                  <span className="ml-1 text-sm font-normal text-muted-foreground">
                    pts
                  </span>
                </p>
              )}
            </CardContent>
          </Card>
        </div>
      )}

      <Card>
        <CardHeader>
          <CardTitle>Transactions</CardTitle>
        </CardHeader>
        <CardContent>
          {transactionsError ? (
            <ErrorState
              title="Could not load transactions"
              message={transactionsError}
              onRetry={() => void fetchTransactions(page)}
            />
          ) : showTransactionsSkeleton ? (
            <div className="space-y-3">
              {Array.from({ length: 5 }).map((_, index) => (
                <Skeleton key={index} className="h-16 w-full rounded-lg" />
              ))}
            </div>
          ) : transactions.length === 0 ? (
            <EmptyState
              icon={Receipt}
              title="No transactions yet"
              description="Starter points, escrow, releases, and refunds will appear here."
            />
          ) : (
            <div>
              {transactions.map((transaction) => (
                <TransactionRow
                  key={transaction.id}
                  transaction={transaction}
                />
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      {meta && meta.last_page > 1 ? (
        <Pagination
          page={meta.current_page}
          lastPage={meta.last_page}
          onPageChange={setPage}
          disabled={transactionsLoading}
        />
      ) : null}
    </div>
  )
}
