import { useId, useState, type KeyboardEvent } from "react"
import { Plus, X } from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"

export function SkillsInput({
  value,
  onChange,
  disabled = false,
  maxSkills = 30,
  minSkillLength = 1,
  maxSkillLength = 50,
  label = "Skills",
  hint = "Add skills so teammates know what you are good at.",
  placeholder = "e.g. React, copywriting, UX research",
}: {
  value: string[]
  onChange: (skills: string[]) => void
  disabled?: boolean
  maxSkills?: number
  minSkillLength?: number
  maxSkillLength?: number
  label?: string
  hint?: string
  placeholder?: string
}) {
  const inputId = useId()
  const [draft, setDraft] = useState("")
  const [error, setError] = useState<string | null>(null)

  function addSkill() {
    const skill = draft.trim()

    if (!skill) {
      setError("Type a skill before adding it.")
      return
    }

    if (skill.length < minSkillLength) {
      setError(`Skills must be at least ${minSkillLength} characters.`)
      return
    }

    if (skill.length > maxSkillLength) {
      setError(`Skills can be at most ${maxSkillLength} characters.`)
      return
    }

    if (value.some((item) => item.toLowerCase() === skill.toLowerCase())) {
      setError("That skill has already been added.")
      return
    }

    if (value.length >= maxSkills) {
      setError(`You can add up to ${maxSkills} skills.`)
      return
    }

    onChange([...value, skill])
    setDraft("")
    setError(null)
  }

  function handleKeyDown(event: KeyboardEvent<HTMLInputElement>) {
    if (event.key === "Enter" || event.key === ",") {
      event.preventDefault()
      addSkill()
    }
  }

  function removeSkill(skill: string) {
    onChange(value.filter((item) => item !== skill))
    setError(null)
  }

  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between gap-2">
        <Label htmlFor={inputId}>{label}</Label>
        <span className="text-xs text-muted-foreground">
          {value.length}/{maxSkills}
        </span>
      </div>

      <div className="flex gap-2">
        <Input
          id={inputId}
          value={draft}
          onChange={(event) => {
            setDraft(event.target.value)
            setError(null)
          }}
          onKeyDown={handleKeyDown}
          placeholder={placeholder}
          maxLength={maxSkillLength}
          disabled={disabled || value.length >= maxSkills}
        />
        <Button
          type="button"
          variant="outline"
          onClick={addSkill}
          disabled={disabled || value.length >= maxSkills}
        >
          <Plus className="size-4" />
          Add
        </Button>
      </div>

      {error ? <p className="text-sm text-destructive">{error}</p> : null}

      {value.length > 0 ? (
        <div className="flex flex-wrap gap-1.5 pt-1">
          {value.map((skill) => (
            <Badge key={skill} variant="secondary" className="gap-1 pr-1">
              {skill}
              <button
                type="button"
                onClick={() => removeSkill(skill)}
                disabled={disabled}
                className="rounded-sm p-0.5 text-secondary-foreground/70 transition-colors hover:bg-secondary-foreground/10 hover:text-secondary-foreground disabled:opacity-50"
                aria-label={`Remove ${skill}`}
              >
                <X className="size-3" />
              </button>
            </Badge>
          ))}
        </div>
      ) : (
        <p className="text-sm text-muted-foreground">{hint}</p>
      )}
    </div>
  )
}
