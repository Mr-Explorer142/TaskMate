import { useEffect, useId, useRef, useState } from "react"
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
import { useExchangeRates } from "@/hooks/useExchangeRates"
import { useViewerCurrency } from "@/hooks/useViewerCurrency"
import {
  convertCurrencyToUsd,
  convertUsdToCurrency,
  FALLBACK_CURRENCY,
  formatMoney,
  hasUsdRate,
} from "@/lib/currencies"
import type { TaskFilters as TaskFiltersValue, TaskSort, TaskType } from "@/types"

const SKILL_SUGGESTIONS = [
  "Bookkeeping",
  "Content Writing",
  "Copywriting",
  "Customer Support",
  "Data Entry",
  "Event Planning",
  "Fundraising",
  "Grant Writing",
  "Graphic Design",
  "HR Admin",
  "Lesson Planning",
  "Legal Admin",
  "Market Research",
  "Music Editing",
  "Photo Editing",
  "Project Planning",
  "Proofreading",
  "Real Estate",
  "Social Media",
  "Survey Design",
  "Translation",
  "Travel Planning",
  "Video Editing",
  "Volunteer Coordination",
]

const SORT_OPTIONS: Array<{ value: TaskSort; label: string }> = [
  { value: "-created_at", label: "Newest" },
  { value: "-reward", label: "Highest reward" },
  { value: "deadline", label: "Soonest deadline" },
]

function usdToInput(
  value: number | undefined,
  currency: string,
  rates: Record<string, number>
): string {
  if (value === undefined) {
    return ""
  }

  const converted = convertUsdToCurrency(value, currency, rates) ?? value

  return String(Number(converted.toFixed(2)))
}

function inputToUsd(
  value: string,
  currency: string,
  rates: Record<string, number>
): number | undefined {
  const trimmed = value.trim()

  if (trimmed === "") {
    return undefined
  }

  const parsed = Number(trimmed)

  if (!Number.isFinite(parsed) || parsed < 0) {
    return undefined
  }

  const usd = convertCurrencyToUsd(parsed, currency, rates)

  if (usd === null || !Number.isFinite(usd)) {
    return undefined
  }

  return Number(usd.toFixed(2))
}

function usdEquivalent(
  value: string,
  currency: string,
  rates: Record<string, number>
): string | null {
  const usd = inputToUsd(value, currency, rates)

  return usd === undefined ? null : formatMoney(usd, FALLBACK_CURRENCY)
}

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
  const { currency: rewardCurrency } = useViewerCurrency()
  const { rates, status: ratesStatus, refresh } = useExchangeRates([
    rewardCurrency,
  ])
  const localRateReady = hasUsdRate(rewardCurrency, rates)
  const filterCurrency = localRateReady ? rewardCurrency : FALLBACK_CURRENCY
  const waitingForRates = rewardCurrency !== FALLBACK_CURRENCY && !localRateReady
  const ratesLoading = waitingForRates && ratesStatus !== "fallback"
  const ratesUnavailable = waitingForRates && ratesStatus === "fallback"
  const skipMinSyncRef = useRef(false)
  const skipMaxSyncRef = useRef(false)
  const [search, setSearch] = useState(filters.search ?? "")
  const [skill, setSkill] = useState(filters.skill ?? "")
  const [minReward, setMinReward] = useState(() =>
    usdToInput(filters.min_reward, filterCurrency, rates)
  )
  const [maxReward, setMaxReward] = useState(() =>
    usdToInput(filters.max_reward, filterCurrency, rates)
  )

  const debouncedSearch = useDebounce(search)
  const debouncedSkill = useDebounce(skill)
  const minUsdHint = usdEquivalent(minReward, filterCurrency, rates)
  const maxUsdHint = usdEquivalent(maxReward, filterCurrency, rates)

  useEffect(() => {
    setSearch(filters.search ?? "")
  }, [filters.search])

  useEffect(() => {
    setSkill(filters.skill ?? "")
  }, [filters.skill])

  useEffect(() => {
    if (skipMinSyncRef.current) {
      skipMinSyncRef.current = false
      return
    }

    setMinReward(usdToInput(filters.min_reward, filterCurrency, rates))
  }, [filters.min_reward, rates, filterCurrency])

  useEffect(() => {
    if (skipMaxSyncRef.current) {
      skipMaxSyncRef.current = false
      return
    }

    setMaxReward(usdToInput(filters.max_reward, filterCurrency, rates))
  }, [filters.max_reward, rates, filterCurrency])

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

  function handleMinRewardChange(value: string) {
    setMinReward(value)
    skipMinSyncRef.current = true
    onChange({ min_reward: inputToUsd(value, filterCurrency, rates) })
  }

  function handleMaxRewardChange(value: string) {
    setMaxReward(value)
    skipMaxSyncRef.current = true
    onChange({ max_reward: inputToUsd(value, filterCurrency, rates) })
  }

  return (
    <Card>
      <CardContent className="space-y-4 p-4">
        {ratesUnavailable ? (
          <div className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border bg-muted/30 px-3 py-2 text-xs text-muted-foreground">
            <span>Exchange rates are unavailable. Reward filters are using USD.</span>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={() => void refresh()}
            >
              Retry rates
            </Button>
          </div>
        ) : null}

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
              placeholder="e.g. Bookkeeping"
              disabled={disabled}
            />
            <datalist id={skillListId}>
              {SKILL_SUGGESTIONS.map((suggestion) => (
                <option key={suggestion} value={suggestion} />
              ))}
            </datalist>
          </div>

          <div className="space-y-2">
            <Label htmlFor="task-min-reward">Min reward ({filterCurrency})</Label>
            <Input
              id="task-min-reward"
              type="number"
              min={0}
              step="0.01"
              value={minReward}
              onChange={(event) => handleMinRewardChange(event.target.value)}
              placeholder="0"
              disabled={disabled || ratesLoading}
            />
            <p className="text-xs text-muted-foreground">
              {ratesLoading
                ? "Converting to USD…"
                : minUsdHint !== null && filterCurrency !== FALLBACK_CURRENCY
                  ? `≈ ${minUsdHint} USD`
                  : "Amounts are filtered in USD."}
            </p>
          </div>

          <div className="space-y-2">
            <Label htmlFor="task-max-reward">Max reward ({filterCurrency})</Label>
            <Input
              id="task-max-reward"
              type="number"
              min={0}
              step="0.01"
              value={maxReward}
              onChange={(event) => handleMaxRewardChange(event.target.value)}
              placeholder="Any"
              disabled={disabled || ratesLoading}
            />
            <p className="text-xs text-muted-foreground">
              {ratesLoading
                ? "Converting to USD…"
                : maxUsdHint !== null && filterCurrency !== FALLBACK_CURRENCY
                  ? `≈ ${maxUsdHint} USD`
                  : "Amounts are filtered in USD."}
            </p>
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
