import { cn } from "cn"

import { createElement } from "react"

import { countryFlag, countryName } from "@/lib/countries"

export function CountryFlag({
  code,
  showName = false,
  className,
}: {
  code: string | null | undefined
  showName?: boolean
  className?: string
}) {
  const name = countryName(code)
  const Flag = countryFlag(code)

  if (!name || !Flag) {
    return null
  }

  return (
    <span
      className={cn("inline-flex items-center gap-1.5 align-middle", className)}
      title={name}
    >
      <CountryFlagIcon countryCode={code} />
      {showName ? <span>{name}</span> : <span className="sr-only">{name}</span>}
    </span>
  )
}

export function CountryFlagIcon({
  countryCode,
  className,
}: {
  countryCode: string | null | undefined
  className?: string
}) {
  const Flag = countryFlag(countryCode)

  if (!Flag) {
    return null
  }

  return (
    <span
      aria-hidden="true"
      className={cn(
        "inline-flex h-3.5 w-5 shrink-0 overflow-hidden rounded-[2px] ring-1 ring-black/10",
        className
      )}
    >
      {createElement(Flag, { className: "size-full" })}
    </span>
  )
}
