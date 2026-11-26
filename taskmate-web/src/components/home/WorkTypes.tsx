import { Coins, Wallet } from "lucide-react"

import { Card, CardContent } from "@/components/ui/card"

const workTypes = [
  {
    title: "Paid tasks",
    description:
      "Agree on an amount with the task uploader. Payment happens outside TaskMate, and TaskMate records the agreed reward.",
    icon: Wallet,
  },
  {
    title: "Points tasks",
    description:
      "Earn points for finished work. Points are held in escrow when the project starts and released to the team when it is completed.",
    icon: Coins,
  },
]

export function WorkTypes() {
  return (
    <section className="border-t border-border py-16 sm:py-20">
      <div className="mx-auto w-full max-w-6xl px-4">
        <h2 className="text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
          Paid or points, your choice
        </h2>

        <div className="mt-8 grid gap-4 sm:grid-cols-2">
          {workTypes.map((workType) => (
            <Card key={workType.title} className="h-full">
              <CardContent className="flex h-full flex-col gap-3">
                <span className="flex size-9 items-center justify-center rounded-lg bg-secondary text-primary">
                  <workType.icon className="size-5" aria-hidden="true" />
                </span>
                <p className="font-medium text-foreground">{workType.title}</p>
                <p className="text-sm leading-relaxed text-muted-foreground">
                  {workType.description}
                </p>
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
    </section>
  )
}
