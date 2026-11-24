import { useEffect, useId, useState } from "react"
import { RotateCcw, Search } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Switch } from "@/components/ui/switch"
import { useDebounce } from "@/hooks/useDebounce"
import type { TaskFilters as TaskFiltersValue, TaskSort, TaskType } from "@/types"

const SKILL_SUGGESTIONS = [
  "Laravel",
  "React",
  "PHP",
  "MySQL",
  "UI Design",
  "Figma",
  "Content Writing",
  "SEO",
  "Python",
  "Data Entry",
  "Video Editing",
  "Translation",
]

const SORT_OPTIONS: Array<{ value: TaskSort; label: string }> = [
  { value: "-created_at", label: "Newest" },
  { value: "-reward", label: "Highest reward" },
  { value: "deadline", label: "Soonest deadline" },
]

export function TaskFilters({
  filters,
  onChange,
  onClear,
  disabled = false,
}: {
  filters: TaskFiltersValue
  onChange: (patch: Partial<TaskFiltersValue>) => void
  onClear: () => void
  disabled?: boolean
}) {
  const skillListId = useId()
  const [search, setSearch] = useState(filters.search ?? "")
  const [skill, setSkill] = useState(filters.skill ?? "")
  const [minReward, setMinReward] = useState(
    filters.min_reward !== undefined ? String(filters.min_reward) : ""
  )
  const [maxReward, setMaxReward] = useState(
    filters.max_reward !== undefined ? String(filters.max_reward) : ""
  )

  const debouncedSearch = useDebounce(search)
  const debouncedSkill = useDebounce(skill)
  const debouncedMinReward = useDebounce(minReward)
  const debouncedMaxReward = useDebounce(maxReward)

  useEffect(() => {
    setSearch(filters.search ?? "")
  }, [filters.search])

  useEffect(() => {
    setSkill(filters.skill ?? "")
  }, [filters.skill])

  useEffect(() => {
    setMinReward(
      filters.min_reward !== undefined ? String(filters.min_reward) : ""
    )
  }, [filters.min_reward])

  useEffect(() => {
    setMaxReward(
      filters.max_reward !== undefined ? String(filters.max_reward) : ""
    )
  }, [filters.max_reward])

  useEffect(() => {
    const next = debouncedSearch.trim()

    if (next === (filters.search ?? "")) {
      return
    }

    onChange({ search: next || undefined })
  }, [debouncedSearch, filters.search, onChange])

  useEffect(() => {
    const next = debouncedSkill.trim()

    if (next === (filters.skill ?? "")) {
      return
    }

    onChange({ skill: next || undefined })
  }, [debouncedSkill, filters.skill, onChange])

  useEffect(() => {
    const trimmed = debouncedMinReward.trim()
    const parsed = trimmed === "" ? undefined : Number(trimmed)
    const next =
      parsed !== undefined && Number.isFinite(parsed) ? parsed : undefined

    if (next === filters.min_reward) {
      return
    }

    onChange({ min_reward: next })
  }, [debouncedMinReward, filters.min_reward, onChange])

  useEffect(() => {
    const trimmed = debouncedMaxReward.trim()
    const parsed = trimmed === "" ? undefined : Number(trimmed)
    const next =
      parsed !== undefined && Number.isFinite(parsed) ? parsed : undefined

    if (next === filters.max_reward) {
      return
    }

    onChange({ max_reward: next })
  }, [debouncedMaxReward, filters.max_reward, onChange])

  return (
    <Card>
      <CardContent className="space-y-4 p-4">
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <div className="space-y-2 sm:col-span-2">
            <Label htmlFor="task-search">Search</Label>
            <div className="relative">
              <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                id="task-search"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Search task titles and descriptions"
                className="pl-8"
                disabled={disabled}
              />
            </div>
          </div>

          <div className="space-y-2">
            <Label htmlFor="task-type">Type</Label>
            <Select
              value={filters.type ?? "all"}
              onValueChange={(value) =>
                onChange({
                  type: value === "all" ? undefined : (value as TaskType),
                })
              }
              disabled={disabled}
            >
              <SelectTrigger id="task-type" className="w-full">
                <SelectValue placeholder="All types" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All types</SelectItem>
                <SelectItem value="paid">Paid</SelectItem>
                <SelectItem value="points">Points</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label htmlFor="task-sort">Sort</Label>
            <Select
              value={filters.sort ?? "-created_at"}
              onValueChange={(value) => onChange({ sort: value as TaskSort })}
              disabled={disabled}
            >
              <SelectTrigger id="task-sort" className="w-full">
                <SelectValue placeholder="Newest" />
              </SelectTrigger>
              <SelectContent>
                {SORT_OPTIONS.map((option) => (
                  <SelectItem key={option.value} value={option.value}>
                    {option.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2 sm:col-span-2">
            <Label htmlFor="task-skill">Skill</Label>
            <Input
              id="task-skill"
              list={skillListId}
              value={skill}
              onChange={(event) => setSkill(event.target.value)}
              placeholder="e.g. Laravel"
              disabled={disabled}
            />
            <datalist id={skillListId}>
              {SKILL_SUGGESTIONS.map((suggestion) => (
                <option key={suggestion} value={suggestion} />
              ))}
            </datalist>
          </div>

          <div className="space-y-2">
            <Label htmlFor="task-min-reward">Min reward</Label>
            <Input
              id="task-min-reward"
              type="number"
              min={0}
              step="0.01"
              value={minReward}
              onChange={(event) => setMinReward(event.target.value)}
              placeholder="0"
              disabled={disabled}
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="task-max-reward">Max reward</Label>
            <Input
              id="task-max-reward"
              type="number"
              min={0}
              step="0.01"
              value={maxReward}
              onChange={(event) => setMaxReward(event.target.value)}
              placeholder="Any"
              disabled={disabled}
            />
          </div>

          <div className="flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-2 sm:col-span-2 lg:col-span-4">
            <Label htmlFor="task-open-slots" className="font-normal">
              Has open slots
            </Label>
            <Switch
              id="task-open-slots"
              checked={Boolean(filters.has_open_slots)}
              onCheckedChange={(checked) =>
                onChange({ has_open_slots: checked || undefined })
              }
              disabled={disabled}
            />
          </div>
        </div>

        <div className="flex justify-end">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={onClear}
            disabled={disabled}
          >
            <RotateCcw className="size-4" />
            Clear filters
          </Button>
        </div>
      </CardContent>
    </Card>
  )
}
