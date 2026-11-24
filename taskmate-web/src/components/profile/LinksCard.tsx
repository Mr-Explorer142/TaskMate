import { useCallback, useEffect, useMemo, useState } from "react"
import { zodResolver } from "@hookform/resolvers/zod"
import {
  ArrowDown,
  ArrowUp,
  Link as LinkIcon,
  Loader2,
  Pencil,
  Plus,
  Trash2,
} from "lucide-react"
import { useForm } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import { getApiErrorMessage, getFieldErrors } from "@/api/client"
import {
  createLink,
  deleteLink,
  listLinks,
  reorderLinks,
  updateLink,
  type LinkPayload,
} from "@/api/profile"
import { EmptyState, ErrorState } from "@/components/PageStates"
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
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form"
import { Input } from "@/components/ui/input"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { detectPlatform, isHttpUrl, platformLabel } from "@/lib/platform"
import type { LinkPlatform, ProfileLink } from "@/types"

const PLATFORMS: LinkPlatform[] = [
  "github",
  "linkedin",
  "portfolio",
  "behance",
  "dribbble",
  "twitter",
  "youtube",
  "website",
  "other",
]

const platformValues = ["auto", ...PLATFORMS] as const

const linkSchema = z.object({
  url: z
    .string()
    .trim()
    .min(1, "URL is required.")
    .max(2048, "URL is too long.")
    .refine(isHttpUrl, "Enter a valid http:// or https:// URL."),
  label: z
    .string()
    .trim()
    .max(60, "Label must be 60 characters or fewer.")
    .optional(),
  platform: z.enum(platformValues),
})

type LinkFormValues = z.infer<typeof linkSchema>

function LinkEditor({
  initial,
  submitLabel,
  onSubmit,
  onCancel,
}: {
  initial?: ProfileLink
  submitLabel: string
  onSubmit: (payload: LinkPayload) => Promise<void>
  onCancel?: () => void
}) {
  const form = useForm<LinkFormValues>({
    resolver: zodResolver(linkSchema),
    defaultValues: {
      url: initial?.url ?? "",
      label: initial?.label ?? "",
      platform: initial?.platform ?? "auto",
    },
  })

  const url = form.watch("url")
  const platform = form.watch("platform")

  const detected = useMemo<LinkPlatform | null>(() => {
    if (platform !== "auto" || !isHttpUrl(url.trim())) {
      return null
    }

    return detectPlatform(url.trim())
  }, [platform, url])

  async function handleSubmit(values: LinkFormValues) {
    try {
      await onSubmit({
        url: values.url.trim(),
        label: values.label?.trim() ? values.label.trim() : null,
        platform: values.platform === "auto" ? null : values.platform,
      })
      form.reset({ url: "", label: "", platform: "auto" })
    } catch (error) {
      const fieldErrors = getFieldErrors(error)

      for (const field of ["url", "label", "platform"] as const) {
        if (fieldErrors[field]) {
          form.setError(field, { message: fieldErrors[field] })
        }
      }

      if (Object.keys(fieldErrors).length === 0) {
        toast.error(getApiErrorMessage(error))
      }
    }
  }

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-4">
        <FormField
          control={form.control}
          name="url"
          render={({ field }) => (
            <FormItem>
              <FormLabel>URL</FormLabel>
              <FormControl>
                <Input
                  placeholder="https://github.com/yourname"
                  autoComplete="url"
                  {...field}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <div className="grid gap-4 sm:grid-cols-2">
          <FormField
            control={form.control}
            name="label"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Label (optional)</FormLabel>
                <FormControl>
                  <Input placeholder="My portfolio" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="platform"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Platform</FormLabel>
                <Select value={field.value} onValueChange={field.onChange}>
                  <FormControl>
                    <SelectTrigger className="w-full">
                      <SelectValue placeholder="Auto-detect" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value="auto">Auto-detect</SelectItem>
                    {PLATFORMS.map((item) => (
                      <SelectItem key={item} value={item}>
                        {platformLabel(item)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>

        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-2 text-sm text-muted-foreground">
            {detected ? (
              <>
                <PlatformIcon platform={detected} />
                <span>
                  Detected:{" "}
                  <span className="font-medium text-foreground">
                    {platformLabel(detected)}
                  </span>
                </span>
              </>
            ) : (
              <span>Pick a platform or leave it on auto-detect.</span>
            )}
          </div>

          <div className="flex gap-2">
            {onCancel ? (
              <Button type="button" variant="outline" onClick={onCancel}>
                Cancel
              </Button>
            ) : null}
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? (
                <Loader2 className="size-4 animate-spin" />
              ) : initial ? null : (
                <Plus className="size-4" />
              )}
              {form.formState.isSubmitting ? "Saving..." : submitLabel}
            </Button>
          </div>
        </div>
      </form>
    </Form>
  )
}

export function LinksCard() {
  const [links, setLinks] = useState<ProfileLink[] | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [pendingReorder, setPendingReorder] = useState(false)
  const [deleteTarget, setDeleteTarget] = useState<ProfileLink | null>(null)
  const [deleting, setDeleting] = useState(false)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)

    try {
      setLinks(await listLinks())
    } catch (loadError) {
      setError(getApiErrorMessage(loadError))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  async function handleCreate(payload: LinkPayload) {
    await createLink(payload)
    toast.success("Link added.")
    await load()
  }

  async function handleUpdate(id: number, payload: LinkPayload) {
    await updateLink(id, payload)
    toast.success("Link updated.")
    setEditingId(null)
    await load()
  }

  async function handleDelete() {
    if (!deleteTarget) {
      return
    }

    setDeleting(true)

    try {
      await deleteLink(deleteTarget.id)
      toast.success("Link deleted.")
      setDeleteTarget(null)
      await load()
    } catch (deleteError) {
      toast.error(getApiErrorMessage(deleteError))
    } finally {
      setDeleting(false)
    }
  }

  async function move(index: number, direction: -1 | 1) {
    if (!links || pendingReorder) {
      return
    }

    const targetIndex = index + direction

    if (targetIndex < 0 || targetIndex >= links.length) {
      return
    }

    const previous = links
    const next = [...links]
    ;[next[index], next[targetIndex]] = [next[targetIndex], next[index]]

    setLinks(next)
    setPendingReorder(true)

    try {
      setLinks(await reorderLinks(next.map((link) => link.id)))
    } catch (reorderError) {
      setLinks(previous)
      toast.error(getApiErrorMessage(reorderError))
    } finally {
      setPendingReorder(false)
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Profile links</CardTitle>
        <CardDescription>
          Add your GitHub, LinkedIn, portfolio, and anything else worth sharing.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-5">
        {loading ? (
          <div className="space-y-2">
            {[0, 1, 2].map((item) => (
              <div
                key={item}
                className="flex items-center gap-3 rounded-lg border border-border p-3"
              >
                <Skeleton className="size-9 rounded-lg" />
                <div className="flex-1 space-y-2">
                  <Skeleton className="h-4 w-32" />
                  <Skeleton className="h-3 w-52" />
                </div>
                <Skeleton className="h-8 w-8" />
              </div>
            ))}
          </div>
        ) : error ? (
          <ErrorState message={error} onRetry={() => void load()} />
        ) : links && links.length > 0 ? (
          <ul className="space-y-2">
            {links.map((link, index) => (
              <li key={link.id}>
                {editingId === link.id ? (
                  <div className="rounded-lg border border-border bg-background p-4">
                    <LinkEditor
                      key={link.id}
                      initial={link}
                      submitLabel="Save link"
                      onSubmit={(payload) => handleUpdate(link.id, payload)}
                      onCancel={() => setEditingId(null)}
                    />
                  </div>
                ) : (
                  <div className="flex items-center gap-3 rounded-lg border border-border bg-background p-3">
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-secondary text-secondary-foreground">
                      <PlatformIcon platform={link.platform} />
                    </span>

                    <div className="min-w-0 flex-1">
                      <div className="flex items-center gap-2">
                        <p className="truncate font-medium text-foreground">
                          {link.label || platformLabel(link.platform)}
                        </p>
                        <Badge variant="outline" className="hidden sm:inline-flex">
                          {platformLabel(link.platform)}
                        </Badge>
                      </div>
                      <a
                        href={link.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="block truncate text-sm text-info hover:underline"
                      >
                        {link.url}
                      </a>
                    </div>

                    <div className="flex shrink-0 items-center gap-1">
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        onClick={() => void move(index, -1)}
                        disabled={index === 0 || pendingReorder}
                        aria-label="Move link up"
                      >
                        <ArrowUp className="size-4" />
                      </Button>
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        onClick={() => void move(index, 1)}
                        disabled={index === links.length - 1 || pendingReorder}
                        aria-label="Move link down"
                      >
                        <ArrowDown className="size-4" />
                      </Button>
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        onClick={() => setEditingId(link.id)}
                        aria-label="Edit link"
                      >
                        <Pencil className="size-4" />
                      </Button>
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        className="text-destructive hover:text-destructive"
                        onClick={() => setDeleteTarget(link)}
                        aria-label="Delete link"
                      >
                        <Trash2 className="size-4" />
                      </Button>
                    </div>
                  </div>
                )}
              </li>
            ))}
          </ul>
        ) : (
          <EmptyState
            icon={LinkIcon}
            title="No links yet"
            description="Add your first link below so people can find your work."
          />
        )}

        <div className="rounded-lg border border-border bg-muted/30 p-4">
          <h3 className="mb-4 text-sm font-medium text-foreground">Add a link</h3>
          {loading ? (
            <div className="space-y-3">
              <Skeleton className="h-4 w-16" />
              <Skeleton className="h-9 w-full" />
              <Skeleton className="h-9 w-full" />
            </div>
          ) : (
            <LinkEditor submitLabel="Add link" onSubmit={handleCreate} />
          )}
        </div>
      </CardContent>

      <AlertDialog
        open={deleteTarget !== null}
        onOpenChange={(open) => {
          if (!open) {
            setDeleteTarget(null)
          }
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete this link?</AlertDialogTitle>
            <AlertDialogDescription>
              {deleteTarget?.label || platformLabel(deleteTarget?.platform ?? "website")}{" "}
              will be removed from your profile.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleting}>Cancel</AlertDialogCancel>
            <AlertDialogAction
              variant="destructive"
              disabled={deleting}
              onClick={(event) => {
                event.preventDefault()
                void handleDelete()
              }}
            >
              {deleting ? "Deleting..." : "Delete link"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </Card>
  )
}
