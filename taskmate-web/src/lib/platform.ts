import type { LinkPlatform } from "@/types"

const PLATFORM_LABELS: Record<LinkPlatform, string> = {
  github: "GitHub",
  linkedin: "LinkedIn",
  portfolio: "Portfolio",
  behance: "Behance",
  dribbble: "Dribbble",
  twitter: "Twitter / X",
  youtube: "YouTube",
  website: "Website",
  other: "Link",
}

export function platformLabel(platform: LinkPlatform): string {
  return PLATFORM_LABELS[platform] ?? "Link"
}

function hostMatches(host: string, domain: string): boolean {
  return host === domain || host.endsWith(`.${domain}`)
}

export function detectPlatform(url: string): LinkPlatform {
  let host = ""

  try {
    host = new URL(url).hostname.toLowerCase().replace(/^www\./, "")
  } catch {
    return "website"
  }

  if (host === "") {
    return "website"
  }

  if (hostMatches(host, "github.com")) return "github"
  if (hostMatches(host, "linkedin.com")) return "linkedin"
  if (hostMatches(host, "behance.net")) return "behance"
  if (hostMatches(host, "dribbble.com")) return "dribbble"
  if (hostMatches(host, "x.com") || hostMatches(host, "twitter.com")) return "twitter"
  if (hostMatches(host, "youtube.com") || hostMatches(host, "youtu.be")) return "youtube"

  return "website"
}

export function isHttpUrl(value: string): boolean {
  try {
    const url = new URL(value)
    return (url.protocol === "http:" || url.protocol === "https:") && url.hostname.length > 0
  } catch {
    return false
  }
}
