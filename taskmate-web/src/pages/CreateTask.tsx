import { Info } from "lucide-react"

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { usePageTitle } from "@/hooks/usePageTitle"

import { TaskForm } from "@/components/tasks/TaskForm"

export function CreateTaskPage() {
  usePageTitle("Post a task")
  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <div className="space-y-1">
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">
          Post a task
        </h1>
        <p className="text-sm text-muted-foreground">
          Describe the work and the teammates you need.
        </p>
      </div>

      <div className="flex items-start gap-3 rounded-lg border border-border bg-muted/30 p-3 text-sm text-muted-foreground">
        <Info className="mt-0.5 size-4 shrink-0 text-primary" />
        <p>
          You must hire a complete fresher, or a candidate with one year of
          experience or less. You cannot hire someone experienced on this
          platform. TaskMate is built to help freshers grow.
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Task details</CardTitle>
        </CardHeader>
        <CardContent>
          <TaskForm />
        </CardContent>
      </Card>
    </div>
  )
}
