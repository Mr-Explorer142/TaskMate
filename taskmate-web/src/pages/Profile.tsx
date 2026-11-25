import { usePageTitle } from "@/hooks/usePageTitle"
import { ExternalLink, Star } from "lucide-react"
import { Link } from "react-router-dom"

import { me } from "@/api/auth"
import { CountryFlag } from "@/components/CountryFlag"
import { ErrorState } from "@/components/PageStates"
import { PageLoadingSkeleton } from "@/components/ProtectedRoute"
import { BasicInfoCard } from "@/components/profile/BasicInfoCard"
import { CvCard } from "@/components/profile/CvCard"
import { LinksCard } from "@/components/profile/LinksCard"
import { ReviewsReceived } from "@/components/reviews/ReviewsReceived"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import { formatRatingLabel } from "@/lib/format"
import { getInitials } from "@/lib/utils"
import { useAuthStore } from "@/stores/useAuthStore"

export function ProfilePage() {
  usePageTitle("Profile")
  const user = useAuthStore((state) => state.user)
  const token = useAuthStore((state) => state.token)
  const isHydrating = useAuthStore((state) => state.isHydrating)
  const setUser = useAuthStore((state) => state.setUser)
  const setHydrating = useAuthStore((state) => state.setHydrating)
  const clearAuth = useAuthStore((state) => state.clearAuth)

  async function retry() {
    setHydrating(true)

    try {
      setUser(await me())
    } catch {
      clearAuth()
    } finally {
      setHydrating(false)
    }
  }

  if (isHydrating) {
    return <PageLoadingSkeleton />
  }

  if (!user) {
    return (
      <ErrorState
        title="Could not load your profile"
        message="We could not refresh your account details. Please try again."
        onRetry={token ? () => void retry() : undefined}
      />
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-3">
          <Avatar className="size-12">
            {user.avatar ? <AvatarImage src={user.avatar} alt={user.name} /> : null}
            <AvatarFallback className="bg-secondary font-semibold text-secondary-foreground">
              {getInitials(user.name)}
            </AvatarFallback>
          </Avatar>
          <div>
            <div className="flex flex-wrap items-center gap-2">
              <h1 className="text-xl font-semibold tracking-tight text-foreground">
                Your profile
              </h1>
              <span className="flex items-center gap-1 text-sm font-medium text-foreground">
                <Star className="size-3.5 fill-primary text-primary" />
                {formatRatingLabel(user.average_rating, user.reviews_count)}
              </span>
              <CountryFlag code={user.country} />
            </div>
            <p className="text-sm text-muted-foreground">
              Keep your details and links up to date.
            </p>
          </div>
        </div>

        <Button variant="outline" asChild>
          <Link to={`/users/${user.id}`}>
            <ExternalLink className="size-4" />
            View public profile
          </Link>
        </Button>
      </div>

      <BasicInfoCard user={user} />
      <CvCard user={user} />
      <LinksCard />
      <ReviewsReceived userId={user.id} />
    </div>
  )
}
