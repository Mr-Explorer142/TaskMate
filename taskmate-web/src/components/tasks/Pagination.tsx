import { ChevronLeft, ChevronRight } from "lucide-react"

import { Button } from "@/components/ui/button"

export function Pagination({
  page,
  lastPage,
  onPageChange,
  disabled = false,
}: {
  page: number
  lastPage: number
  onPageChange: (page: number) => void
  disabled?: boolean
}) {
  return (
    <div className="flex flex-wrap items-center justify-center gap-3">
      <Button
        variant="outline"
        size="sm"
        onClick={() => onPageChange(page - 1)}
        disabled={disabled || page <= 1}
      >
        <ChevronLeft className="size-4" />
        Previous
      </Button>
      <span className="text-sm text-muted-foreground">
        Page {page} of {Math.max(1, lastPage)}
      </span>
      <Button
        variant="outline"
        size="sm"
        onClick={() => onPageChange(page + 1)}
        disabled={disabled || page >= lastPage}
      >
        Next
        <ChevronRight className="size-4" />
      </Button>
    </div>
  )
}
