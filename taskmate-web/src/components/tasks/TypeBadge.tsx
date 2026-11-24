import { Badge } from "@/components/ui/badge"
import type { TaskType } from "@/types"

export function TypeBadge({ type }: { type: TaskType }) {
  return (
    <Badge
      variant="outline"
      className={
        type === "paid"
          ? "border-primary/20 bg-primary/10 text-primary"
          : "border-info/20 bg-info/10 text-info"
      }
    >
      {type === "paid" ? "Paid" : "Points"}
    </Badge>
  )
}
