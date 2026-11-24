import { ArrowRight, Sparkles } from "lucide-react"
import { Link } from "react-router-dom"

import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { useAuthStore } from "@/stores/useAuthStore"

export function HomePage() {
  const user = useAuthStore((state) => state.user)

  return (
    <div className="space-y-8">
      <section className="rounded-lg border border-border bg-card p-6 shadow-sm sm:p-10">
        <div className="flex items-center gap-2 text-sm font-medium text-primary">
          <Sparkles className="size-4" />
          Day 1
        </div>
        <h1 className="mt-3 max-w-2xl text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">
          Welcome{user ? `, ${user.name.split(" ")[0]}` : ""}. Find your next team.
        </h1>
        <p className="mt-3 max-w-2xl text-muted-foreground">
          TaskMate helps people team up on real work. For now, get your profile
          ready — tasks arrive on Day 2.
        </p>
        <div className="mt-6 flex flex-wrap gap-2">
          <Button asChild>
            <Link to="/profile">
              Set up your profile
              <ArrowRight className="size-4" />
            </Link>
          </Button>
          {user ? (
            <Button variant="outline" asChild>
              <Link to={`/users/${user.id}`}>View your public profile</Link>
            </Button>
          ) : null}
        </div>
      </section>

      <Card className="border-dashed">
        <CardContent className="flex flex-col items-start gap-3 py-6 sm:flex-row sm:items-center">
          <img
            src="/taskmate-icon.svg"
            alt="TaskMate"
            className="size-10 shrink-0 rounded-lg"
          />
          <div className="flex-1">
            <p className="font-medium text-foreground">Tasks coming Day 2</p>
            <p className="text-sm text-muted-foreground">
              Posting, browsing, and applying for tasks will land here next.
            </p>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
