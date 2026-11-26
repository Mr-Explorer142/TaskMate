import { Link } from "react-router-dom"

import { Button } from "@/components/ui/button"

export function Hero() {
  return (
    <section className="relative mx-auto w-full max-w-6xl px-3 pb-16 pt-14 sm:px-4 sm:pb-24 sm:pt-20 lg:px-0 lg:py-0">
      <div className="grid items-center gap-6 rounded-3xl border border-border bg-background p-4 shadow-sm sm:gap-8 sm:p-6 lg:min-h-[32rem] lg:grid-cols-2 lg:items-stretch lg:gap-0 lg:rounded-none lg:border-0 lg:bg-transparent lg:p-0 lg:shadow-none">
        <div className="relative z-10 space-y-5 lg:py-12 lg:pl-4 lg:self-center">
          <h1 className="text-3xl font-semibold tracking-tight text-foreground sm:text-4xl lg:text-5xl">
            Find a team for your next task
          </h1>
          <p className="max-w-xl text-base leading-relaxed text-muted-foreground sm:text-lg">
            Post a task, pick your teammates, finish the work together, and
            review each other.
          </p>
          <div className="flex flex-wrap gap-3 pt-1">
            <Button asChild size="lg">
              <Link to="/tasks">Browse tasks</Link>
            </Button>
            <Button asChild variant="outline" size="lg">
              <Link to="/tasks/new">Post a task</Link>
            </Button>
          </div>
        </div>

        <div className="relative aspect-[4/3] overflow-hidden rounded-2xl lg:aspect-auto lg:h-full lg:rounded-none">
          <img
            src="/images/hero-light.png"
            alt="Team collaborating on a task"
            width={1200}
            height={900}
            loading="eager"
            fetchPriority="high"
            className="absolute inset-0 block h-full w-full object-cover dark:hidden lg:object-right"
          />
          <img
            src="/images/hero-dark.png"
            alt="Team collaborating on a task"
            width={1200}
            height={900}
            loading="eager"
            fetchPriority="high"
            className="absolute inset-0 hidden h-full w-full object-cover dark:block lg:object-right"
          />
          <div className="pointer-events-none absolute inset-0 hidden bg-background/20 dark:block" />
          <div className="pointer-events-none absolute inset-x-0 top-0 h-1/3 bg-linear-to-b from-background to-transparent lg:hidden" />
          <div className="pointer-events-none absolute inset-y-0 left-0 hidden w-1/3 bg-linear-to-r from-background to-transparent lg:block" />
        </div>

        <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border pt-4 text-xs text-muted-foreground lg:hidden">
          <span className="flex items-center gap-1.5">
            <span className="size-1.5 rounded-full bg-primary" />
            Points or paid
          </span>
          <span className="flex items-center gap-1.5">
            <span className="size-1.5 rounded-full bg-primary" />
            Pick your team
          </span>
          <span className="flex items-center gap-1.5">
            <span className="size-1.5 rounded-full bg-primary" />
            Review together
          </span>
        </div>
      </div>
    </section>
  )
}
