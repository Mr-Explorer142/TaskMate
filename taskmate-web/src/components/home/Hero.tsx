import { CalendarDays, Star } from "lucide-react"
import { Link } from "react-router-dom"

import { SlotsIndicator } from "@/components/tasks/SlotsIndicator"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"

const exampleSkills = ["Figma", "UI design", "Webflow"]

export function Hero() {
  return (
    <section className="mx-auto w-full max-w-6xl px-4 pb-16 pt-14 sm:pb-24 sm:pt-20">
      <div className="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
        <div className="space-y-5">
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

        <div className="space-y-2">
          <Card className="border-border">
            <CardContent className="flex flex-col gap-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap items-center gap-1.5">
                  <Badge variant="secondary">Paid</Badge>
                  <Badge variant="outline">Open</Badge>
                </div>
                <span className="text-base font-semibold text-foreground">
                  $450.00
                </span>
              </div>

              <div>
                <p className="text-base font-semibold text-foreground">
                  Landing page design
                </p>
                <div className="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                  <span className="flex size-5 items-center justify-center rounded-full bg-secondary text-[10px] font-semibold text-secondary-foreground">
                    AM
                  </span>
                  <span>Alex Morgan</span>
                  <span className="flex items-center gap-1">
                    <Star className="size-3 fill-primary text-primary" />
                    4.8 (12)
                  </span>
                </div>
              </div>

              <div className="flex flex-wrap gap-1.5">
                {exampleSkills.map((skill) => (
                  <Badge key={skill} variant="secondary">
                    {skill}
                  </Badge>
                ))}
              </div>

              <div className="mt-1 space-y-2.5">
                <SlotsIndicator accepted={2} teamSize={4} />
                <div className="flex items-center justify-between text-xs text-muted-foreground">
                  <span className="flex items-center gap-1.5">
                    <CalendarDays className="size-3.5" />
                    Due in 3 weeks
                  </span>
                  <span>2 slots open</span>
                </div>
              </div>
            </CardContent>
          </Card>
          <p className="px-1 text-xs text-muted-foreground">Example task</p>
        </div>
      </div>
    </section>
  )
}
