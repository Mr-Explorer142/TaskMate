import { Coins, LogOut, User as UserIcon } from "lucide-react"
import { Link } from "react-router-dom"

import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { getInitials } from "@/lib/utils"
import type { User } from "@/types"

export function UserMenu({
  user,
  loggingOut,
  onLogout,
}: {
  user: User
  loggingOut: boolean
  onLogout: () => void
}) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="ghost"
          className="h-10 gap-2 px-1.5"
          aria-label="Account menu"
        >
          <Avatar className="size-8">
            {user.avatar_url ? (
              <AvatarImage src={user.avatar_url} alt={user.name} />
            ) : null}
            <AvatarFallback className="bg-secondary text-xs font-semibold text-secondary-foreground">
              {getInitials(user.name)}
            </AvatarFallback>
          </Avatar>
          <span className="hidden max-w-32 truncate text-sm font-medium xl:inline">
            {user.name}
          </span>
        </Button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="end" className="w-56">
        <DropdownMenuLabel className="truncate">
          {user.name}
          <span className="block truncate text-xs font-normal text-muted-foreground">
            {user.email}
          </span>
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuItem asChild>
          <Link to="/profile">
            <UserIcon className="size-4" />
            Profile
          </Link>
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <Link to="/points">
            <Coins className="size-4" />
            Points
          </Link>
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem
          variant="destructive"
          disabled={loggingOut}
          onSelect={(event) => {
            event.preventDefault()
            onLogout()
          }}
        >
          <LogOut className="size-4" />
          {loggingOut ? "Logging out..." : "Logout"}
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
