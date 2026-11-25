import { useState } from "react"
import { format } from "date-fns"
import { Download, FileText, Loader2, Star } from "lucide-react"
import { toast } from "sonner"

import { getApiErrorMessage } from "@/api/client"
import { downloadCv } from "@/api/profile"
import { CountryFlag } from "@/components/CountryFlag"
import { PlatformIcon } from "@/components/profile/PlatformIcon"
import { ReviewsReceived } from "@/components/reviews/ReviewsReceived"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { EmptyState } from "@/components/PageStates"
import { formatRatingLabel } from "@/lib/format"
import { platformLabel } from "@/lib/platform"
import { saveBlob } from "@/lib/download"
import { getInitials } from "@/lib/utils"
import type { PublicUser } from "@/types"

export function ProfileView({ user }: { user: PublicUser }) {
  const [downloading, setDownloading] = useState(false)

  async function handleDownloadCv() {
    setDownloading(true)

    try {
      const { blob, filename } = await downloadCv(user.id)
      saveBlob(blob, filename)
    } catch (error) {
      toast.error(getApiErrorMessage(error))
    } finally {
      setDownloading(false)
    }
  }

  return (
    <div className="space-y-6">
      <Card>
        <CardContent className="flex flex-col gap-5 pt-4 sm:flex-row sm:items-start">
          <Avatar className="size-16 shrink-0">
            {user.avatar ? (
              <AvatarImage src={user.avatar} alt={user.name} />
            ) : null}
            <AvatarFallback className="bg-secondary text-lg font-semibold text-secondary-foreground">
              {getInitials(user.name)}
            </AvatarFallback>
          </Avatar>

          <div className="min-w-0 flex-1 space-y-2">
            <div>
              <div className="flex flex-wrap items-center gap-2">
                <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                  {user.name}
                </h1>
                <span className="flex items-center gap-1 text-sm font-medium text-foreground">
                  <Star className="size-3.5 fill-primary text-primary" />
                  {formatRatingLabel(user.average_rating, user.reviews_count)}
                </span>
                <CountryFlag code={user.country} showName className="text-sm text-muted-foreground" />
              </div>
              <p className="text-sm text-muted-foreground">
                Member since {format(new Date(user.created_at), "MMMM yyyy")}
              </p>
            </div>

            {user.bio ? (
              <p className="max-w-2xl whitespace-pre-line text-sm text-muted-foreground">
                {user.bio}
              </p>
            ) : (
              <p className="text-sm text-muted-foreground">
                This member has not added a bio yet.
              </p>
            )}
          </div>

          {user.has_cv ? (
            <Button
              type="button"
              variant="outline"
              onClick={() => void handleDownloadCv()}
              disabled={downloading}
            >
              {downloading ? (
                <Loader2 className="size-4 animate-spin" />
              ) : (
                <Download className="size-4" />
              )}
              {downloading ? "Downloading..." : "Download CV"}
            </Button>
          ) : null}
        </CardContent>
      </Card>

      <div className="grid gap-6 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Skills</CardTitle>
            <CardDescription>What this member works with.</CardDescription>
          </CardHeader>
          <CardContent>
            {user.skills.length > 0 ? (
              <div className="flex flex-wrap gap-1.5">
                {user.skills.map((skill) => (
                  <Badge key={skill} variant="secondary">
                    {skill}
                  </Badge>
                ))}
              </div>
            ) : (
              <p className="text-sm text-muted-foreground">
                No skills listed yet.
              </p>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Links</CardTitle>
            <CardDescription>Find this member elsewhere.</CardDescription>
          </CardHeader>
          <CardContent>
            {user.profile_links.length > 0 ? (
              <ul className="flex flex-wrap gap-2">
                {user.profile_links.map((link) => (
                  <li key={link.id}>
                    <a
                      href={link.url}
                      target="_blank"
                      rel="noopener noreferrer"
                      title={link.label || platformLabel(link.platform)}
                      aria-label={link.label || platformLabel(link.platform)}
                      className="flex size-10 items-center justify-center rounded-lg border border-border bg-background text-muted-foreground transition-colors hover:border-primary/40 hover:bg-secondary hover:text-secondary-foreground"
                    >
                      <PlatformIcon platform={link.platform} className="size-4.5" />
                    </a>
                  </li>
                ))}
              </ul>
            ) : (
              <EmptyState
                icon={FileText}
                title="No links yet"
                description="This member has not added any links."
              />
            )}
          </CardContent>
        </Card>
      </div>

      <ReviewsReceived userId={user.id} />
    </div>
  )
}
