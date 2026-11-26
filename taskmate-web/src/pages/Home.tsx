import { useEffect } from "react"

import { FinalCTA } from "@/components/home/FinalCTA"
import { Hero } from "@/components/home/Hero"
import { HowItWorks } from "@/components/home/HowItWorks"
import { LatestTasks } from "@/components/home/LatestTasks"
import { TrustSection } from "@/components/home/TrustSection"
import { WorkTypes } from "@/components/home/WorkTypes"

export function HomePage() {
  useEffect(() => {
    document.title = "TaskMate | Find a team for your next task"
  }, [])

  return (
    <>
      <Hero />
      <HowItWorks />
      <LatestTasks />
      <WorkTypes />
      <TrustSection />
      <FinalCTA />
    </>
  )
}
