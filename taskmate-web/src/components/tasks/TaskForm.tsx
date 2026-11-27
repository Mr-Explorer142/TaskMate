import { useEffect } from "react"
import { zodResolver } from "@hookform/resolvers/zod"
import { Loader2 } from "lucide-react"
import { useForm } from "react-hook-form"
import { Link, useNavigate } from "react-router-dom"
import { toast } from "sonner"
import { z } from "zod"

import { getApiErrorMessage, getFieldErrors } from "@/api/client"
import { TaskSkillsInput } from "@/components/tasks/SkillsInput"
import { Button } from "@/components/ui/button"
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group"
import { Textarea } from "@/components/ui/textarea"
import { CURRENCY_SYMBOL, todayInputValue } from "@/lib/format"
import { useTaskStore } from "@/stores/useTaskStore"
import type { Task, TaskPayload } from "@/types"

const REWARD_MAX = 1_000_000
const today = todayInputValue

const taskFormSchema = z
  .object({
    title: z
      .string()
      .trim()
      .min(5, "Title must be at least 5 characters.")
      .max(120, "Title must be at most 120 characters."),
    description: z
      .string()
      .trim()
      .min(20, "Description must be at least 20 characters.")
      .max(5000, "Description must be at most 5000 characters."),
    team_size: z
      .number({ message: "Team size is required." })
      .int("Team size must be a whole number.")
      .min(1, "Team size must be at least 1.")
      .max(20, "Team size must be at most 20."),
    type: z.enum(["paid", "points"]),
    reward: z.string().min(1, "Reward is required."),
    deadline: z.string(),
    skills: z
      .array(z.string().min(2).max(40))
      .max(15, "You can add up to 15 skills."),
  })
  .superRefine((values, ctx) => {
    const reward = values.reward.trim()

    if (values.type === "paid") {
      const amount = Number(reward)

      if (reward === "" || !Number.isFinite(amount)) {
        ctx.addIssue({
          code: "custom",
          path: ["reward"],
          message: "Reward is required.",
        })
      } else if (!/^\d+(\.\d{1,2})?$/.test(reward)) {
        ctx.addIssue({
          code: "custom",
          path: ["reward"],
          message: "Enter an amount with at most 2 decimals.",
        })
      } else if (amount <= 0) {
        ctx.addIssue({
          code: "custom",
          path: ["reward"],
          message: "Paid rewards must be greater than 0.",
        })
      } else if (amount > REWARD_MAX) {
        ctx.addIssue({
          code: "custom",
          path: ["reward"],
          message: "Reward must be at most 1,000,000.",
        })
      }
    } else if (reward === "" || !/^\d+$/.test(reward)) {
      ctx.addIssue({
        code: "custom",
        path: ["reward"],
        message: "Points must be a whole number.",
      })
    } else if (Number(reward) < 1) {
      ctx.addIssue({
        code: "custom",
        path: ["reward"],
        message: "Points must be at least 1.",
      })
    }

    if (values.deadline && values.deadline < today()) {
      ctx.addIssue({
        code: "custom",
        path: ["deadline"],
        message: "Deadline must be today or later.",
      })
    }

    const seen = new Set<string>()

    values.skills.forEach((skill, index) => {
      const key = skill.trim().toLowerCase()

      if (seen.has(key)) {
        ctx.addIssue({
          code: "custom",
          path: ["skills", index],
          message: "That skill has already been added.",
        })
      }

      seen.add(key)
    })
  })

type TaskFormValues = z.infer<typeof taskFormSchema>

function rewardForInput(task?: Task): string {
  if (!task) {
    return ""
  }

  const value = Number(task.reward)

  return task.type === "points"
    ? String(Math.round(value))
    : String(value)
}

export function TaskForm({ task }: { task?: Task }) {
  const navigate = useNavigate()
  const createTask = useTaskStore((state) => state.createTask)
  const updateTask = useTaskStore((state) => state.updateTask)
  const applicationsCount = task?.applications_count ?? 0
  const typeLocked = applicationsCount > 0
  const minimumTeamSize = task ? Math.max(1, task.accepted_count) : 1

  const form = useForm<TaskFormValues>({
    resolver: zodResolver(taskFormSchema),
    defaultValues: {
      title: task?.title ?? "",
      description: task?.description ?? "",
      team_size: task?.team_size ?? 1,
      type: task?.type ?? "paid",
      reward: rewardForInput(task),
      deadline: task?.deadline ?? "",
      skills: task?.skills ?? [],
    },
  })

  const type = form.watch("type")
  const descriptionLength = form.watch("description").length
  const isSubmitting = form.formState.isSubmitting
  const isDirty = form.formState.isDirty

  useEffect(() => {
    if (!isDirty) {
      return
    }

    function handleBeforeUnload(event: BeforeUnloadEvent) {
      event.preventDefault()
      event.returnValue = ""
    }

    window.addEventListener("beforeunload", handleBeforeUnload)

    return () => window.removeEventListener("beforeunload", handleBeforeUnload)
  }, [isDirty])

  async function onSubmit(values: TaskFormValues) {
    const payload: TaskPayload = {
      title: values.title.trim(),
      description: values.description.trim(),
      team_size: values.team_size,
      type: values.type,
      reward: Number(values.reward),
      deadline: values.deadline || null,
      skills: values.skills,
    }

    try {
      const saved = task
        ? await updateTask(task.id, payload)
        : await createTask(payload)

      toast.success(task ? "Task updated." : "Task posted.")
      navigate(`/tasks/${saved.id}`)
    } catch (error) {
      const fieldErrors = getFieldErrors(error)
      const fields = [
        "title",
        "description",
        "team_size",
        "type",
        "reward",
        "deadline",
        "skills",
      ] as const

      for (const field of fields) {
        if (fieldErrors[field]) {
          form.setError(field, { message: fieldErrors[field] })
        }
      }

      const nestedSkillError = Object.keys(fieldErrors).find((key) =>
        key.startsWith("skills.")
      )

      if (nestedSkillError) {
        form.setError("skills", { message: fieldErrors[nestedSkillError] })
      }

      toast.error(getApiErrorMessage(error))
    }
  }

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-5">
        <FormField
          control={form.control}
          name="title"
          render={({ field }) => (
            <FormItem>
              <FormLabel>Title</FormLabel>
              <FormControl>
                <Input
                  placeholder="e.g. Plan a community event"
                  maxLength={120}
                  {...field}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="description"
          render={({ field }) => (
            <FormItem>
              <div className="flex items-center justify-between gap-2">
                <FormLabel>Description</FormLabel>
                <span className="text-xs text-muted-foreground">
                  {descriptionLength}/5000
                </span>
              </div>
              <FormControl>
                <Textarea
                  rows={7}
                  maxLength={5000}
                  placeholder="Describe the work, expectations, and what a teammate should know."
                  {...field}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <div className="grid gap-4 sm:grid-cols-2">
          <FormField
            control={form.control}
            name="team_size"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Team size</FormLabel>
                <FormControl>
                  <Input
                    type="number"
                    min={minimumTeamSize}
                    max={20}
                    value={field.value}
                    onChange={(event) =>
                      field.onChange(
                        event.target.value === ""
                          ? undefined
                          : Number(event.target.value)
                      )
                    }
                    onBlur={field.onBlur}
                    name={field.name}
                    ref={field.ref}
                  />
                </FormControl>
                <FormDescription>
                  {task && task.accepted_count > 0
                    ? `Minimum allowed: ${minimumTeamSize} (accepted members).`
                    : "How many people do you need?"}
                </FormDescription>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="deadline"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Deadline</FormLabel>
                <FormControl>
                  <Input type="date" min={today()} {...field} />
                </FormControl>
                <FormDescription>Optional. Today or later.</FormDescription>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>

        <FormField
          control={form.control}
          name="type"
          render={({ field }) => (
            <FormItem>
              <FormLabel>Type</FormLabel>
              <FormControl>
                <RadioGroup
                  value={field.value}
                  onValueChange={field.onChange}
                  disabled={typeLocked || isSubmitting}
                  className="flex flex-col gap-2 sm:flex-row"
                >
                  <div className="flex items-center gap-2 rounded-lg border border-border px-3 py-2">
                    <RadioGroupItem value="paid" id="task-type-paid" />
                    <Label htmlFor="task-type-paid" className="font-normal">
                      Paid
                    </Label>
                  </div>
                  <div className="flex items-center gap-2 rounded-lg border border-border px-3 py-2">
                    <RadioGroupItem value="points" id="task-type-points" />
                    <Label htmlFor="task-type-points" className="font-normal">
                      Points
                    </Label>
                  </div>
                </RadioGroup>
              </FormControl>
              {typeLocked ? (
                <p className="text-xs text-muted-foreground">
                  The type cannot be changed once the task has applications.
                </p>
              ) : null}
              <FormMessage />
            </FormItem>
          )}
        />

        <div className="grid gap-4 sm:grid-cols-2">
          <FormField
            control={form.control}
            name="reward"
            render={({ field }) => (
              <FormItem>
                <FormLabel>
                  {type === "paid"
                    ? `Reward (${CURRENCY_SYMBOL})`
                    : "Reward (points)"}
                </FormLabel>
                <FormControl>
                  <Input
                    type="number"
                    min={type === "paid" ? 0.01 : 1}
                    max={type === "paid" ? REWARD_MAX : undefined}
                    step={type === "paid" ? "0.01" : "1"}
                    placeholder={type === "paid" ? "250.00" : "50"}
                    {...field}
                  />
                </FormControl>
                <FormDescription>
                  {type === "paid"
                    ? "Amount in dollars, up to 2 decimals."
                    : "Whole number of points."}
                </FormDescription>
                {type === "points" ? (
                  <p className="text-xs text-muted-foreground">
                    Reward is per member. When you start the project, reward x
                    accepted members will be held in escrow.
                  </p>
                ) : null}
                <FormMessage />
              </FormItem>
            )}
          />

          <div className="space-y-2">
            <TaskSkillsInput
              value={form.watch("skills")}
              onChange={(skills) => form.setValue("skills", skills, { shouldDirty: true })}
              disabled={isSubmitting}
            />
            {form.formState.errors.skills ? (
              <p className="text-sm text-destructive">
                {form.formState.errors.skills.message}
              </p>
            ) : null}
          </div>
        </div>

        <div className="flex flex-wrap justify-end gap-2">
          <Button type="button" variant="outline" asChild>
            <Link to={task ? `/tasks/${task.id}` : "/tasks"}>Cancel</Link>
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting ? <Loader2 className="size-4 animate-spin" /> : null}
            {isSubmitting
              ? "Saving..."
              : task
                ? "Save changes"
                : "Post task"}
          </Button>
        </div>
      </form>
    </Form>
  )
}
