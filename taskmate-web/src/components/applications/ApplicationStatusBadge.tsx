import { Badge } from "@/components/ui/badge"
import type { ApplicationStatus } from "@/types"

const LABELS: Record<ApplicationStatus, string> = {
  pending: "Pending",
  accepted: "Accepted",
  rejected: "Rejected",
  left: "Left",
}

const VARIANTS = {
  pending: "pending",
  accepted: "open",
  rejected: "destructive",
  left: "secondary",
} as const

export function ApplicationStatusBadge({
  status,
  label,
}: {
  status: ApplicationStatus
  label?: string
}) {
  return <Badge variant={VARIANTS[status]}>{label ?? LABELS[status]}</Badge>
}
