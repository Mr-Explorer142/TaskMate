import { cn } from "cn"

export function Logo({
  height = "h-8",
  className,
}: {
  height?: string
  className?: string
}) {
  return (
    <span className={cn("inline-flex items-center", className)}>
      <img
        src="/taskmate-logo.svg"
        alt="TaskMate"
        className={cn("w-auto dark:hidden", height)}
      />
      <img
        src="/taskmate-logo-white.svg"
        alt="TaskMate"
        className={cn("hidden w-auto dark:block", height)}
      />
    </span>
  )
}
