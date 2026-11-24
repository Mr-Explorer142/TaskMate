import { CountryFlagIcon } from "@/components/CountryFlag"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { countries } from "@/lib/countries"

export function CountrySelect({
  id,
  value,
  onChange,
  disabled,
}: {
  id?: string
  value: string | null | undefined
  onChange: (value: string) => void
  disabled?: boolean
}) {
  return (
    <Select value={value ?? undefined} onValueChange={onChange} disabled={disabled}>
      <SelectTrigger id={id} className="w-full">
        <SelectValue placeholder="Select your country" />
      </SelectTrigger>
      <SelectContent className="max-h-72">
        {countries.map((country) => (
          <SelectItem key={country.code} value={country.code}>
            <CountryFlagIcon countryCode={country.code} className="h-3 w-[18px]" />
            <span>{country.name}</span>
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}
