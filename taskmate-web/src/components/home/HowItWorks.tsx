import { ClipboardList, MessageCircle, Star, Users } from "lucide-react"

import { Card, CardContent } from "@/components/ui/card"

const steps = [
  {
    title: "Post or find a task",
    description:
      "Share what you need done, or browse tasks that match your skills.",
    icon: ClipboardList,
  },
  {
    title: "Apply and get accepted",
    description:
      "Applicants send a short message. The uploader checks profiles and CVs before accepting.",
    icon: MessageCircle,
  },
  {
    title: "Work as a team",
    description:
      "Chat with your team. If a member leaves, the open slot can be filled again.",
    icon: Users,
  },
  {
    title: "Review each other",
    description:
      "When the project is finished, everyone reviews each other and ratings build trust.",
    icon: Star,
  },
]

export function HowItWorks() {
  return (
    <section
      id="how-it-works"
      className="scroll-mt-20 border-y border-border bg-muted/30"
    >
      <div className="mx-auto w-full max-w-6xl px-4 py-16 sm:py-20">
        <h2 className="text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
          How TaskMate works
        </h2>

        <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {steps.map((step, index) => (
            <Card key={step.title} className="h-full">
              <CardContent className="flex h-full flex-col gap-3">
                <span className="flex size-9 items-center justify-center rounded-lg bg-secondary text-primary">
                  <step.icon className="size-5" aria-hidden="true" />
                </span>
                <p className="font-medium text-foreground">
                  {index + 1}. {step.title}
                </p>
                <p className="text-sm leading-relaxed text-muted-foreground">
                  {step.description}
                </p>
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
    </section>
  )
}
