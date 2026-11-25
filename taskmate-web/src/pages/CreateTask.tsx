import { usePageTitle } from "@/hooks/usePageTitle"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"

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
