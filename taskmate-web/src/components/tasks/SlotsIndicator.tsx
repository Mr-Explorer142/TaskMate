export function SlotsIndicator({
  accepted,
  teamSize,
}: {
  accepted: number
  teamSize: number
}) {
  const percent =
    teamSize > 0 ? Math.min(100, Math.round((accepted / teamSize) * 100)) : 0

  return (
    <div className="space-y-1.5">
      <div className="flex items-center justify-between text-xs">
        <span className="text-muted-foreground">Team slots</span>
        <span className="font-medium text-foreground">
          {accepted} / {teamSize} filled
        </span>
      </div>
      <div
        role="progressbar"
        aria-label="Filled team slots"
        aria-valuenow={accepted}
        aria-valuemin={0}
        aria-valuemax={teamSize}
        className="h-1.5 w-full overflow-hidden rounded-full bg-muted"
      >
        <div
          className="h-full rounded-full bg-primary transition-all"
          style={{ width: `${percent}%` }}
        />
      </div>
    </div>
  )
}
