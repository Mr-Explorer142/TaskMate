import { useRef, useState } from "react"
import type { KeyboardEvent as ReactKeyboardEvent } from "react"
import { Star } from "lucide-react"

import { formatRatingLabel } from "@/lib/format"
import { cn } from "@/lib/utils"

export function StarRating({
  value,
  count,
  compact = false,
}: {
  value: number | null | undefined
  count?: number
  compact?: boolean
}) {
  const rounded = value == null ? 0 : Math.round(value)

  return (
    <div className="flex flex-wrap items-center gap-2">
      <div className="flex items-center gap-0.5" aria-hidden="true">
        {[1, 2, 3, 4, 5].map((star) => (
          <Star
            key={star}
            className={cn(
              compact ? "size-3.5" : "size-4",
              star <= rounded
                ? "fill-primary text-primary"
                : "text-border"
            )}
          />
        ))}
      </div>
      <span className="text-sm font-medium text-foreground">
        {formatRatingLabel(value, count)}
      </span>
    </div>
  )
}

export function RatingInput({
  value,
  onChange,
  disabled = false,
}: {
  value: number
  onChange: (rating: number) => void
  disabled?: boolean
}) {
  const buttons = useRef<Array<HTMLButtonElement | null>>([])
  const [focused, setFocused] = useState(value)

  function select(star: number) {
    setFocused(star)
    onChange(star)
  }

  function handleKeyDown(
    event: ReactKeyboardEvent<HTMLButtonElement>,
    star: number
  ) {
    let next: number | null = null

    switch (event.key) {
      case "ArrowLeft":
      case "ArrowDown":
        next = Math.max(1, star - 1)
        break
      case "ArrowRight":
      case "ArrowUp":
        next = Math.min(5, star + 1)
        break
      case "Home":
        next = 1
        break
      case "End":
        next = 5
        break
      default:
        return
    }

    event.preventDefault()
    select(next)
    buttons.current[next - 1]?.focus()
  }

  return (
    <div className="flex items-center gap-1" role="radiogroup" aria-label="Rating">
      {[1, 2, 3, 4, 5].map((star) => (
        <button
          key={star}
          ref={(element) => {
            buttons.current[star - 1] = element
          }}
          type="button"
          role="radio"
          aria-checked={value === star}
          aria-label={`${star} out of 5 stars`}
          tabIndex={star === focused ? 0 : -1}
          disabled={disabled}
          onFocus={() => setFocused(star)}
          onClick={() => select(star)}
          onKeyDown={(event) => handleKeyDown(event, star)}
          className="rounded-lg p-0.5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-60"
        >
          <Star
            className={cn(
              "size-7",
              star <= value
                ? "fill-primary text-primary"
                : "text-border hover:text-primary"
            )}
          />
        </button>
      ))}
    </div>
  )
}
