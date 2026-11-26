import { LockKeyhole, ShieldCheck, Star } from "lucide-react"

const trustItems = [
  {
    title: "Check before you accept",
    description:
      "Profiles, links and CVs are visible to the task uploader.",
    icon: ShieldCheck,
  },
  {
    title: "Reviews both ways",
    description:
      "Every team member reviews every other member.",
    icon: Star,
  },
  {
    title: "Escrow for points",
    description:
      "Points are locked at the start and released at completion.",
    icon: LockKeyhole,
  },
]

export function TrustSection() {
  return (
    <section className="py-16 sm:py-20">
      <div className="mx-auto w-full max-w-6xl px-4">
        <h2 className="text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
          Built for trust
        </h2>

        <div className="mt-8 grid gap-6 sm:grid-cols-3">
          {trustItems.map((item) => (
            <div key={item.title} className="flex gap-3">
              <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-secondary text-primary">
                <item.icon className="size-5" aria-hidden="true" />
              </span>
              <div className="space-y-1">
                <p className="font-medium text-foreground">{item.title}</p>
                <p className="text-sm leading-relaxed text-muted-foreground">
                  {item.description}
                </p>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}
