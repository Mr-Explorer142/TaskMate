import { useCallback, useEffect, useState } from "react"
import axios from "axios"
import { ArrowLeft } from "lucide-react"
import { Link, useParams } from "react-router-dom"

import { getApiErrorMessage } from "@/api/client"
import { getUser } from "@/api/profile"
import { ErrorState } from "@/components/PageStates"
import { ProfileView } from "@/components/profile/ProfileView"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Skeleton } from "@/components/ui/skeleton"
import type { PublicUser } from "@/types"

function UserProfileSkeleton() {
  return (
    <div className="space-y-6">
      <Card>
        <CardContent className="flex flex-col gap-5 pt-4 sm:flex-row">
          <Skeleton className="size-16 rounded-full" />
          <div className="flex-1 space-y-3">
            <Skeleton className="h-7 w-48" />
            <Skeleton className="h-4 w-40" />
            <Skeleton className="h-4 w-full max-w-xl" />
            <Skeleton className="h-4 w-3/4 max-w-lg" />
          </div>
        </CardContent>
      </Card>
      <div className="grid gap-6 md:grid-cols-2">
        <Skeleton className="h-40 rounded-lg" />
        <Skeleton className="h-40 rounded-lg" />
      </div>
    </div>
  )
}

export function UserProfilePage() {
  const { id } = useParams<{ id: string }>()
  const [user, setUser] = useState<PublicUser | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notFound, setNotFound] = useState(false)

  const userId = Number(id)

  const load = useCallback(async () => {
    if (!Number.isFinite(userId) || userId <= 0) {
      setNotFound(true)
      setLoading(false)
      return
    }

    setLoading(true)
    setError(null)
    setNotFound(false)

    try {
      setUser(await getUser(userId))
    } catch (loadError) {
      if (axios.isAxiosError(loadError) && loadError.response?.status === 404) {
        setNotFound(true)
      } else {
        setError(getApiErrorMessage(loadError))
      }
    } finally {
      setLoading(false)
    }
  }, [userId])

  useEffect(() => {
    void load()
  }, [load])

  if (loading) {
    return <UserProfileSkeleton />
  }

  if (notFound) {
    return (
      <Card>
        <CardContent className="flex flex-col items-center gap-4 py-12 text-center">
          <div className="space-y-1">
            <h1 className="text-xl font-semibold text-foreground">
              Profile not found
            </h1>
            <p className="text-sm text-muted-foreground">
              This member may have removed their account or the link is wrong.
            </p>
          </div>
          <Button variant="outline" asChild>
            <Link to="/">
              <ArrowLeft className="size-4" />
              Back home
            </Link>
          </Button>
        </CardContent>
      </Card>
    )
  }

  if (error || !user) {
    return (
      <ErrorState
        title="Could not load profile"
        message={error ?? "Please try again."}
        onRetry={() => void load()}
      />
    )
  }

  return (
    <div className="space-y-4">
      <Link
        to="/"
        className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
      >
        <ArrowLeft className="size-4" />
        Back
      </Link>
      <ProfileView user={user} />
    </div>
  )
}
