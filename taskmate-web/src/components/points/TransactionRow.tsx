import { format, parseISO } from "date-fns"
import { Link } from "react-router-dom"

import { Badge } from "@/components/ui/badge"
import type { PointTransaction, PointTransactionType } from "@/types"

const TYPE_STYLES: Record<
  PointTransactionType,
  { label: string; className: string }
> = {
  bonus: {
    label: "Bonus",
    className: "bg-status-open-bg text-status-open",
  },
  escrow: {
    label: "Escrow",
    className: "bg-status-pending-bg text-status-pending",
  },
  release: {
    label: "Release",
    className: "bg-status-in-progress-bg text-status-in-progress",
  },
  refund: {
    label: "Refund",
    className: "bg-status-completed-bg text-status-completed",
  },
}

function formatDate(value: string | null): string {
  if (!value) {
    return ""
  }

  try {
    return format(parseISO(value), "MMM d, yyyy 'at' h:mm a")
  } catch {
    return ""
  }
}

export function TransactionRow({
  transaction,
}: {
  transaction: PointTransaction
}) {
  const style = TYPE_STYLES[transaction.type]
  const positive = transaction.amount > 0

  return (
    <div className="flex items-start justify-between gap-3 border-b border-border py-3 last:border-b-0">
      <div className="min-w-0 space-y-1">
        <div className="flex flex-wrap items-center gap-2">
          <Badge className={style.className}>{style.label}</Badge>
          {transaction.task ? (
            <Link
              to={`/tasks/${transaction.task.id}`}
              className="truncate text-sm font-medium text-foreground hover:text-primary hover:underline"
            >
              {transaction.task.title}
            </Link>
          ) : null}
        </div>
        {transaction.note ? (
          <p className="text-sm text-muted-foreground">{transaction.note}</p>
        ) : null}
        <p className="text-xs text-muted-foreground">
          {formatDate(transaction.created_at)}
        </p>
      </div>

      <span
        className={[
          "shrink-0 text-sm font-semibold tabular-nums",
          positive ? "text-status-open" : "text-destructive",
        ].join(" ")}
      >
        {positive ? "+" : ""}
        {transaction.amount.toLocaleString("en-US")}
      </span>
    </div>
  )
}
