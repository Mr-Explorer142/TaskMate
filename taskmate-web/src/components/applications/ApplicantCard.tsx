import { useState } from "react"
import {
  Download,
  ExternalLink,
  Loader2,
  Star,
} from "lucide-react"
import { Link } from "react-router-dom"
import { toast } from "sonner"

import { getApiErrorMessage } from "@/api/client"
import { downloadCv } from "@/api/profile"
import { ApplicationStatusBadge } from "@/components/applications/ApplicationStatusBadge"
import { PlatformIcon } from "@/components/profile/PlatformIcon"
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { formatPostedDate } from "@/lib/format"
import { saveBlob } from "@/lib/download"
import { platformLabel } from "@/lib/platform"
import { getInitials } from "@/lib/utils"
import type { Application } from "@/types"

type ConfirmAction = "accept" | "reject" | null

export function ApplicantCard({
  application,
  teamFull,
  locked = false,
  busy,
  onAccept,
  onReject,
}: {
  application: Application
  teamFull: boolean
  locked?: boolean
  busy: boolean
  onAccept: (id: number) => void
  onReject: (id: number) => void
}) {
  const applicant = application.applicant
  const [downloading, setDownloading] = useState(false)
  const [confirmAction, setConfirmAction] = useState<ConfirmAction>(null)

  async function handleDownloadCv() {
    if (!applicant) {
      return
    }

    setDownloading(true)

    try {
      const { blob, filename } = await downloadCv(applicant.id)
      saveBlob(blob, filename)
    } catch (error) {
      toast.error(getApiErrorMessage(error))
    } finally {
      setDownloading(false)
    }
  }

  function confirm() {
    const action = confirmAction
    setConfirmAction(null)

    if (action === "accept") {
      onAccept(application.id)
    }

    if (action === "reject") {
      onReject(application.id)
    }
  }

  return (
    <Card>
      <CardContent className="space-y-4">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div className="flex min-w-0 items-start gap-3">
            <Avatar className="size-11 shrink-0">
              {applicant?.avatar ? (
                <AvatarImage src={applicant.avatar} alt={applicant.name} />
              ) : null}
              <AvatarFallback className="bg-secondary text-sm font-semibold text-secondary-foreground">
                {getInitials(applicant?.name ?? "Applicant")}
              </AvatarFallback>
            </Avatar>

            <div className="min-w-0 space-y-1">
              <div className="flex flex-wrap items-center gap-2">
                <p className="truncate font-semibold text-foreground">
                  {applicant?.name ?? "Applicant"}
                </p>
                <ApplicationStatusBadge status={application.status} />
              </div>
              <p className="flex items-center gap-1 text-sm text-muted-foreground">
                <Star className="size-3.5 fill-status-pending text-status-pending" />
                {applicant?.average_rating != null
                  ? `${Number(applicant.average_rating).toFixed(1)} rating`
                  : "No ratings yet"}
              </p>
              <p className="text-xs text-muted-foreground">
                Applied {formatPostedDate(application.created_at)}
              </p>
            </div>
          </div>
        </div>

        {applicant?.bio ? (
          <p className="whitespace-pre-line text-sm text-muted-foreground">
            {applicant.bio}
          </p>
        ) : (
          <p className="text-sm text-muted-foreground">No bio provided.</p>
        )}

        {applicant && applicant.skills.length > 0 ? (
          <div className="flex flex-wrap gap-1.5">
            {applicant.skills.map((skill) => (
              <Badge key={skill} variant="secondary">
                {skill}
              </Badge>
            ))}
          </div>
        ) : null}

        {applicant && applicant.profile_links.length > 0 ? (
          <ul className="flex flex-wrap gap-2">
            {applicant.profile_links.map((link) => (
              <li key={link.id}>
                <a
                  href={link.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  title={link.label || platformLabel(link.platform)}
                  aria-label={link.label || platformLabel(link.platform)}
                  className="flex size-9 items-center justify-center rounded-lg border border-border bg-background text-muted-foreground transition-colors hover:border-primary/40 hover:bg-secondary hover:text-secondary-foreground"
                >
                  <PlatformIcon platform={link.platform} className="size-4" />
                </a>
              </li>
            ))}
          </ul>
        ) : null}

        <div className="rounded-lg border border-border bg-muted/30 p-3">
          {application.message ? (
            <p className="whitespace-pre-line text-sm text-foreground">
              {application.message}
            </p>
          ) : (
            <p className="text-sm text-muted-foreground italic">
              No message provided.
            </p>
          )}
        </div>

        <div className="flex flex-wrap gap-2 border-t border-border pt-3">
          {applicant ? (
            <Button variant="outline" size="sm" asChild>
              <Link to={`/users/${applicant.id}`}>
                <ExternalLink className="size-4" />
                View profile
              </Link>
            </Button>
          ) : null}

          {applicant?.has_cv ? (
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => void handleDownloadCv()}
              disabled={downloading}
            >
              {downloading ? (
                <Loader2 className="size-4 animate-spin" />
              ) : (
                <Download className="size-4" />
              )}
              {downloading ? "Downloading..." : "View CV"}
            </Button>
          ) : null}

          {application.status === "pending" && !locked ? (
            <>
              <span
                title={teamFull ? "Team is full" : undefined}
                className="inline-flex"
              >
                <Button
                  type="button"
                  size="sm"
                  disabled={busy || teamFull}
                  onClick={() => setConfirmAction("accept")}
                >
                  Accept
                </Button>
              </span>
              <Button
                type="button"
                variant="destructive"
                size="sm"
                disabled={busy}
                onClick={() => setConfirmAction("reject")}
              >
                Reject
              </Button>
            </>
          ) : null}
        </div>
      </CardContent>

      <AlertDialog
        open={confirmAction !== null}
        onOpenChange={(open) => {
          if (!open) {
            setConfirmAction(null)
          }
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {confirmAction === "accept"
                ? "Accept this applicant?"
                : "Reject this applicant?"}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {confirmAction === "accept"
                ? `${applicant?.name ?? "This applicant"} will fill a team slot. Pending applications stay pending.`
                : `${applicant?.name ?? "This applicant"} will not be able to re-apply to this task.`}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={busy}>Cancel</AlertDialogCancel>
            <AlertDialogAction
              variant={confirmAction === "reject" ? "destructive" : "default"}
              disabled={busy}
              onClick={(event) => {
                event.preventDefault()
                confirm()
              }}
            >
              {confirmAction === "accept" ? "Accept" : "Reject"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </Card>
  )
}
