import { ExternalLink } from "lucide-react"
import { Link } from "react-router-dom"

import { CountryFlag } from "@/components/CountryFlag"
import { PageLoadingSkeleton } from "@/components/ProtectedRoute"
import { BasicInfoCard } from "@/components/profile/BasicInfoCard"
import { CvCard } from "@/components/profile/CvCard"
import { LinksCard } from "@/components/profile/LinksCard"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import { getInitials } from "@/lib/utils"
import { useAuthStore } from "@/stores/useAuthStore"

export function ProfilePage() {
  const user = useAuthStore((state) => state.user)

  if (!user) {
    return <PageLoadingSkeleton />
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
            <div className="flex items-center gap-2">
              <h1 className="text-xl font-semibold tracking-tight text-foreground">
                Your profile
              </h1>
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
    </div>
  )
}
