import { Badge } from "@/components/ui/badge"
import type { ApplicationStatus, TaskStatus } from "@/types"

type Status = TaskStatus | ApplicationStatus

const LABELS: Record<Status, string> = {
  open: "Open",
  full: "Full",
  in_progress: "In progress",
  completed: "Completed",
  cancelled: "Cancelled",
  pending: "Pending",
  accepted: "Accepted",
  rejected: "Rejected",
  left: "Left",
}

type BadgeVariant = "open" | "pending" | "inProgress" | "completed" | "destructive"

const VARIANTS: Record<Status, BadgeVariant> = {
  open: "open",
  accepted: "open",
  pending: "pending",
  full: "pending",
  in_progress: "inProgress",
  completed: "completed",
  rejected: "destructive",
  cancelled: "destructive",
  left: "destructive",
}

export function StatusBadge({ status }: { status: Status }) {
  return <Badge variant={VARIANTS[status] ?? "secondary"}>{LABELS[status] ?? status}</Badge>
}
