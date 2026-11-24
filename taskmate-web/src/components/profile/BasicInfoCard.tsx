import { useEffect, useState } from "react"
import { Loader2 } from "lucide-react"
import { toast } from "sonner"

import { updateProfile } from "@/api/profile"
import { getApiErrorMessage, getFieldErrors } from "@/api/client"
import { CountrySelect } from "@/components/CountrySelect"
import { SkillsInput } from "@/components/profile/SkillsInput"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { useAuthStore } from "@/stores/useAuthStore"
import type { User } from "@/types"

const BIO_MAX = 500

export function BasicInfoCard({ user }: { user: User }) {
  const setUser = useAuthStore((state) => state.setUser)
  const [name, setName] = useState(user.name)
  const [country, setCountry] = useState<string | null>(user.country)
  const [bio, setBio] = useState(user.bio ?? "")
  const [skills, setSkills] = useState<string[]>(user.skills ?? [])
  const [saving, setSaving] = useState(false)
  const [nameError, setNameError] = useState<string | null>(null)

  useEffect(() => {
    setName(user.name)
    setCountry(user.country)
    setBio(user.bio ?? "")
    setSkills(user.skills ?? [])
  }, [user.id, user.name, user.country, user.bio, user.skills])

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()

    const trimmedName = name.trim()

    if (!trimmedName) {
      setNameError("Name is required.")
      return
    }

    setNameError(null)
    setSaving(true)

    try {
      const updated = await updateProfile({
        name: trimmedName,
        country,
        bio: bio.trim() === "" ? null : bio.trim(),
        skills,
      })
      setUser(updated)
      toast.success("Profile updated.")
    } catch (error) {
      const fieldErrors = getFieldErrors(error)
      setNameError(fieldErrors.name ?? null)
      toast.error(getApiErrorMessage(error))
    } finally {
      setSaving(false)
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Basic information</CardTitle>
        <CardDescription>
          This is what teammates see on your public profile.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit} className="space-y-5">
          <div className="space-y-2">
            <Label htmlFor="profile-name">Name</Label>
            <Input
              id="profile-name"
              value={name}
              onChange={(event) => {
                setName(event.target.value)
                setNameError(null)
              }}
              maxLength={255}
              aria-invalid={Boolean(nameError)}
            />
            {nameError ? (
              <p className="text-sm text-destructive">{nameError}</p>
            ) : null}
          </div>

          <div className="space-y-2">
            <Label htmlFor="profile-country">Country</Label>
            <CountrySelect
              id="profile-country"
              value={country}
              onChange={setCountry}
              disabled={saving}
            />
            <p className="text-xs text-muted-foreground">
              Shown on your public profile with your country flag.
            </p>
          </div>

          <div className="space-y-2">
            <div className="flex items-center justify-between gap-2">
              <Label htmlFor="profile-bio">Bio</Label>
              <span className="text-xs text-muted-foreground">
                {bio.length}/{BIO_MAX}
              </span>
            </div>
            <Textarea
              id="profile-bio"
              value={bio}
              onChange={(event) => setBio(event.target.value)}
              maxLength={BIO_MAX}
              rows={5}
              placeholder="Tell teammates about your background, interests, and what you like to work on."
            />
          </div>

          <SkillsInput value={skills} onChange={setSkills} disabled={saving} />

          <div className="flex justify-end">
            <Button type="submit" disabled={saving}>
              {saving ? <Loader2 className="size-4 animate-spin" /> : null}
              {saving ? "Saving..." : "Save changes"}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  )
}
