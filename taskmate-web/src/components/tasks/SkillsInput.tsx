import { SkillsInput } from "@/components/profile/SkillsInput"

export function TaskSkillsInput({
  value,
  onChange,
  disabled = false,
}: {
  value: string[]
  onChange: (skills: string[]) => void
  disabled?: boolean
}) {
  return (
    <SkillsInput
      value={value}
      onChange={onChange}
      disabled={disabled}
      maxSkills={15}
      minSkillLength={2}
      maxSkillLength={40}
      label="Required skills"
      hint="Add up to 15 skills teammates should have."
      placeholder="e.g. Event Planning, Copywriting, Bookkeeping"
    />
  )
}
