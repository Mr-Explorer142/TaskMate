import type { ReactNode } from "react"
import { Star } from "lucide-react"
import { Link } from "react-router-dom"

import { CountryFlag } from "@/components/CountryFlag"
import { StatusBadge } from "@/components/StatusBadge"
import { SlotsIndicator } from "@/components/tasks/SlotsIndicator"
import { TaskReward } from "@/components/tasks/TaskReward"
import { TypeBadge } from "@/components/tasks/TypeBadge"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent } from "@/components/ui/card"
import { formatDeadline, formatRatingLabel } from "@/lib/format"
import { getInitials } from "@/lib/utils"
import type { Task } from "@/types"

export function TaskCard({
  task,
  actions,
  showApplicants = false,
}: {
  task: Task
  actions?: ReactNode
  showApplicants?: boolean
}) {
  const visibleSkills = task.skills.slice(0, 3)
  const extraSkills = task.skills.length - visibleSkills.length

  return (
    <Card className="h-full transition-colors hover:border-primary/40">
      <CardContent className="flex h-full flex-col gap-3">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div className="flex flex-wrap items-center gap-1.5">
            <TypeBadge type={task.type} />
            <StatusBadge status={task.status} />
          </div>
          <span className="text-base font-semibold text-foreground">
            <TaskReward
              type={task.type}
              reward={task.reward}
              uploaderCountry={task.uploader?.country}
              variant="compact"
            />
          </span>
        </div>

        <div>
          <Link
            to={`/tasks/${task.id}`}
            className="line-clamp-2 text-base font-semibold text-foreground hover:text-primary"
          >
            {task.title}
          </Link>
          <div className="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
            <Avatar className="size-5">
              {task.uploader?.avatar_url ? (
                <AvatarImage src={task.uploader.avatar_url} alt={task.uploader.name} />
              ) : null}
              <AvatarFallback className="bg-secondary text-[10px] font-semibold text-secondary-foreground">
                {getInitials(task.uploader?.name ?? "TaskMate")}
              </AvatarFallback>
            </Avatar>
            <span className="truncate">
              {task.uploader?.name ?? "TaskMate member"}
            </span>
            {task.uploader?.country ? (
              <CountryFlag code={task.uploader.country} />
            ) : null}
            {task.uploader ? (
              <span className="flex items-center gap-1">
                <Star className="size-3 fill-primary text-primary" />
                {formatRatingLabel(
                  task.uploader.average_rating,
                  task.uploader.reviews_count
                )}
              </span>
            ) : null}
          </div>
        </div>

        {task.skills.length > 0 ? (
          <div className="flex flex-wrap gap-1.5">
            {visibleSkills.map((skill) => (
              <Badge key={skill} variant="secondary">
                {skill}
              </Badge>
            ))}
            {extraSkills > 0 ? (
              <Badge variant="outline">+{extraSkills}</Badge>
            ) : null}
          </div>
        ) : null}

        <div className="mt-auto space-y-2.5">
          <SlotsIndicator accepted={task.accepted_count} teamSize={task.team_size} />
          <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
            <span>{formatDeadline(task.deadline)}</span>
            {showApplicants ? (
              <span>{task.pending_count ?? 0} pending</span>
            ) : null}
          </div>
        </div>

        {actions ? (
          <div className="flex flex-wrap gap-2 border-t border-border pt-3">
            {actions}
          </div>
        ) : null}
      </CardContent>
    </Card>
  )
}
