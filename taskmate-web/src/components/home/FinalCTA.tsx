import { Link } from "react-router-dom"

import { Button } from "@/components/ui/button"
import { useAuthStore } from "@/stores/useAuthStore"

export function FinalCTA() {
  const isLoggedIn = Boolean(useAuthStore((state) => state.token))

  return (
    <section className="border-y border-border bg-secondary">
      <div className="mx-auto w-full max-w-6xl px-4 py-14 text-center sm:py-16">
        <h2 className="text-2xl font-semibold tracking-tight text-secondary-foreground sm:text-3xl">
          Ready to start?
        </h2>
        <p className="mt-3 text-sm text-secondary-foreground sm:text-base">
          Join TaskMate and find your next team.
        </p>

        <div className="mt-6 flex flex-wrap items-center justify-center gap-3">
          <Button asChild size="lg">
            <Link to={isLoggedIn ? "/tasks/new" : "/register"}>
              {isLoggedIn ? "Post a task" : "Sign up"}
            </Link>
          </Button>
          <Button asChild variant="ghost" size="lg">
            <Link to="/tasks">Browse tasks</Link>
          </Button>
        </div>
      </div>
    </section>
  )
}
