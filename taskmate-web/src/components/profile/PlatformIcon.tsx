import {
  Dribbble,
  Github,
  Globe,
  Link as LinkIcon,
  Linkedin,
  Palette,
  Twitter,
  Youtube,
} from "lucide-react"

import type { LinkPlatform } from "@/types"
import { cn } from "@/lib/utils"

const ICONS: Record<LinkPlatform, typeof Github> = {
  github: Github,
  linkedin: Linkedin,
  twitter: Twitter,
  youtube: Youtube,
  dribbble: Dribbble,
  behance: Palette,
  portfolio: Globe,
  website: Globe,
  other: LinkIcon,
}

export function PlatformIcon({
  platform,
  className,
}: {
  platform: LinkPlatform
  className?: string
}) {
  const Icon = ICONS[platform] ?? LinkIcon

  return <Icon className={cn("size-4", className)} aria-hidden="true" />
}
