import { usePageTitle } from "@/hooks/usePageTitle"
import { useState } from "react"
import { zodResolver } from "@hookform/resolvers/zod"
import { Loader2 } from "lucide-react"
import { useForm } from "react-hook-form"
import { Link, useNavigate } from "react-router-dom"
import { toast } from "sonner"
import { z } from "zod"

import { register } from "@/api/auth"
import { getApiErrorMessage, getFieldErrors } from "@/api/client"
import { CountrySelect } from "@/components/CountrySelect"
import { AuthShell } from "@/components/layout/AuthShell"
import { SocialAuthButtons } from "@/components/SocialAuthButtons"
import { Button } from "@/components/ui/button"
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form"
import { Input } from "@/components/ui/input"
import { formatPoints } from "@/lib/format"
import { useAuthStore } from "@/stores/useAuthStore"

const registerSchema = z
  .object({
    name: z.string().trim().min(2, "Enter your full name.").max(255),
    email: z.string().min(1, "Email is required.").email("Enter a valid email address."),
    country: z.string().length(2).optional().or(z.literal("")),
    password: z.string().min(8, "Use at least 8 characters."),
    password_confirmation: z.string().min(1, "Confirm your password."),
  })
  .refine((values) => values.password === values.password_confirmation, {
    path: ["password_confirmation"],
    message: "Passwords do not match.",
  })

type RegisterValues = z.infer<typeof registerSchema>

const fieldNames = ["name", "email", "country", "password", "password_confirmation"] as const

export function RegisterPage() {
  usePageTitle("Sign up")
  const setAuth = useAuthStore((state) => state.setAuth)
  const navigate = useNavigate()
  const [submitting, setSubmitting] = useState(false)

  const form = useForm<RegisterValues>({
    resolver: zodResolver(registerSchema),
    defaultValues: {
      name: "",
      email: "",
      country: "",
      password: "",
      password_confirmation: "",
    },
  })

  async function onSubmit(values: RegisterValues) {
    setSubmitting(true)

    try {
      const result = await register({
        name: values.name.trim(),
        email: values.email,
        country: values.country || undefined,
        password: values.password,
        password_confirmation: values.password_confirmation,
      })
      setAuth(result.user, result.token)
      if (result.user.points_balance > 0) {
        toast.success(
          `You received ${formatPoints(result.user.points_balance)} starter points.`
        )
      } else {
        toast.success("Account created. Welcome to TaskMate!")
      }
      navigate("/profile", { replace: true })
    } catch (error) {
      const fieldErrors = getFieldErrors(error)

      for (const field of fieldNames) {
        if (fieldErrors[field]) {
          form.setError(field, { message: fieldErrors[field] })
        }
      }

      toast.error(getApiErrorMessage(error))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <AuthShell
      title="Create your account"
      subtitle="Set up a profile so teammates can get to know you."
      footer={
        <>
          Already have an account?{" "}
          <Link to="/login" className="font-medium text-primary hover:underline">
            Log in
          </Link>
        </>
      }
    >
      <SocialAuthButtons />

      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="mt-5 space-y-4">
          <FormField
            control={form.control}
            name="name"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Full name</FormLabel>
                <FormControl>
                  <Input autoComplete="name" placeholder="Jane Doe" {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="email"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Email</FormLabel>
                <FormControl>
                  <Input
                    type="email"
                    autoComplete="email"
                    placeholder="you@example.com"
                    {...field}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="country"
            render={({ field }) => (
              <FormItem>
                <FormLabel htmlFor="country">Country</FormLabel>
                <CountrySelect
                  id="country"
                  value={field.value}
                  onChange={field.onChange}
                  disabled={submitting}
                />
                <p className="text-xs text-muted-foreground">
                  Your country is shown on your profile with its flag.
                </p>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="password"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Password</FormLabel>
                <FormControl>
                  <Input
                    type="password"
                    autoComplete="new-password"
                    placeholder="At least 8 characters"
                    {...field}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="password_confirmation"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Confirm password</FormLabel>
                <FormControl>
                  <Input
                    type="password"
                    autoComplete="new-password"
                    placeholder="Repeat your password"
                    {...field}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          <Button type="submit" className="w-full" disabled={submitting}>
            {submitting ? <Loader2 className="size-4 animate-spin" /> : null}
            {submitting ? "Creating account..." : "Create account"}
          </Button>
        </form>
      </Form>
    </AuthShell>
  )
}
