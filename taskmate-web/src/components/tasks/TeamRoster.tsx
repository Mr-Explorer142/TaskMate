import { Star, UserPlus } from "lucide-react"
import { Link } from "react-router-dom"

import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { formatRatingLabel } from "@/lib/format"
import { getInitials } from "@/lib/utils"
import type { TeamMember } from "@/types"

export function TeamRoster({
  members,
  teamSize,
  openSlots,
  loading = false,
  error = null,
  onRetry,
}: {
  members: TeamMember[]
  teamSize: number
  openSlots: number
  loading?: boolean
  error?: string | null
  onRetry?: () => void
}) {
  const placeholders = Math.max(0, openSlots)

  return (
    <section className="space-y-3">
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-base font-semibold text-foreground">Team</h2>
        <span className="text-xs text-muted-foreground">
          {members.length} / {teamSize} filled
        </span>
      </div>

      {error ? (
        <div className="flex flex-col items-start gap-2 rounded-lg border border-dashed border-destructive/30 bg-destructive/5 p-4 sm:flex-row sm:items-center sm:justify-between">
          <p className="text-sm text-muted-foreground">{error}</p>
          {onRetry ? (
            <Button variant="outline" size="sm" onClick={onRetry}>
              Try again
            </Button>
          ) : null}
        </div>
      ) : loading && members.length === 0 ? (
        <div className="grid gap-3 sm:grid-cols-2">
          {Array.from({ length: Math.max(1, Math.min(teamSize, 4)) }).map(
            (_, index) => (
              <Skeleton key={index} className="h-[68px] w-full rounded-lg" />
            )
          )}
        </div>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2">
          {members.map((member) => (
            <Link
              key={member.id}
              to={`/users/${member.id}`}
              className="flex min-w-0 items-center gap-3 rounded-lg border border-border bg-card p-3 transition-colors hover:border-primary/40"
            >
              <Avatar className="size-10 shrink-0">
                {member.avatar ? (
                  <AvatarImage src={member.avatar} alt={member.name} />
                ) : null}
                <AvatarFallback className="bg-secondary text-sm font-semibold text-secondary-foreground">
                  {getInitials(member.name)}
                </AvatarFallback>
              </Avatar>

              <div className="min-w-0">
                <p className="truncate font-medium text-foreground">
                  {member.name}
                </p>
                <p className="flex items-center gap-1 text-xs text-muted-foreground">
                  <Star className="size-3 fill-primary text-primary" />
                  {formatRatingLabel(member.average_rating, member.reviews_count)}
                </p>
              </div>
            </Link>
          ))}

          {Array.from({ length: placeholders }).map((_, index) => (
            <div
              key={`open-slot-${index}`}
              className="flex min-w-0 items-center gap-3 rounded-lg border border-dashed border-border bg-muted/30 p-3 text-muted-foreground"
            >
              <span className="flex size-10 shrink-0 items-center justify-center rounded-full border border-dashed border-border bg-background">
                <UserPlus className="size-4" />
              </span>
              <p className="text-sm font-medium">Open slot</p>
            </div>
          ))}
        </div>
      )}
    </section>
  )
}
