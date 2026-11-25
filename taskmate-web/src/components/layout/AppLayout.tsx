import { useState } from "react"
import {
  ClipboardList,
  FileCheck2,
  ListTodo,
  LogOut,
  Plus,
  User as UserIcon,
} from "lucide-react"
import { Link, NavLink, Outlet, useNavigate } from "react-router-dom"
import { toast } from "sonner"

import { logout } from "@/api/auth"
import { CountryFlag } from "@/components/CountryFlag"
import { Footer } from "@/components/layout/Footer"
import { Logo } from "@/components/Logo"
import { ThemeToggle } from "@/components/ThemeToggle"
import { VerifyEmailBanner } from "@/components/VerifyEmailBanner"
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
import { useAuthStore } from "@/stores/useAuthStore"

const navigation = [
  { to: "/tasks", label: "Tasks", icon: ListTodo },
  { to: "/my-tasks", label: "My Tasks", icon: ClipboardList },
  { to: "/my-applications", label: "My Applications", icon: FileCheck2 },
]

export function AppLayout() {
  const user = useAuthStore((state) => state.user)
  const clearAuth = useAuthStore((state) => state.clearAuth)
  const navigate = useNavigate()
  const [loggingOut, setLoggingOut] = useState(false)

  async function handleLogout() {
    setLoggingOut(true)

    try {
      await logout()
    } catch {
      // The local session is cleared even if the token was already invalid.
    } finally {
      clearAuth()
      setLoggingOut(false)
      toast.success("You have been logged out.")
      navigate("/login", { replace: true })
    }
  }

  return (
    <div className="flex min-h-screen flex-col bg-background">
      <header className="sticky top-0 z-40 border-b border-border bg-card">
        <div className="mx-auto flex h-16 w-full max-w-5xl items-center justify-between gap-3 px-4">
          <Link to="/" className="flex items-center">
            <Logo height="h-6 sm:h-8" />
          </Link>

          <nav className="flex items-center gap-0.5 sm:gap-1">
            {navigation.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                className={({ isActive }) =>
                  [
                    "flex items-center gap-1.5 rounded-lg px-1.5 py-1.5 text-sm font-medium transition-colors sm:px-2.5",
                    isActive
                      ? "bg-secondary text-secondary-foreground"
                      : "text-muted-foreground hover:bg-muted hover:text-foreground",
                  ].join(" ")
                }
              >
                <item.icon className="size-4" />
                <span className="hidden sm:inline">{item.label}</span>
              </NavLink>
            ))}
          </nav>

          <div className="flex items-center gap-1">
            <Button asChild size="sm" className="mr-1">
              <Link to="/tasks/new" aria-label="Post a task">
                <Plus className="size-4" />
                <span className="hidden sm:inline">Post a task</span>
              </Link>
            </Button>

            <ThemeToggle />

            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" className="h-10 gap-2 px-1.5">
                  <Avatar className="size-8">
                    {user?.avatar ? (
                      <AvatarImage src={user.avatar} alt={user.name} />
                    ) : null}
                    <AvatarFallback className="bg-secondary text-xs font-semibold text-secondary-foreground">
                      {getInitials(user?.name ?? "TaskMate")}
                    </AvatarFallback>
                  </Avatar>
                  <span className="hidden max-w-32 truncate text-sm font-medium sm:inline">
                    {user?.name}
                  </span>
                  <CountryFlag code={user?.country} />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel className="truncate">
                  <span className="flex items-center gap-1.5">
                    {user?.name}
                    <CountryFlag code={user?.country} />
                  </span>
                  <span className="block truncate text-xs font-normal text-muted-foreground">
                    {user?.email}
                  </span>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                  <Link to="/profile">
                    <UserIcon className="size-4" />
                    Profile
                  </Link>
                </DropdownMenuItem>
                {user ? (
                  <DropdownMenuItem asChild>
                    <Link to={`/users/${user.id}`}>
                      <UserIcon className="size-4" />
                      View public profile
                    </Link>
                  </DropdownMenuItem>
                ) : null}
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  variant="destructive"
                  disabled={loggingOut}
                  onSelect={(event) => {
                    event.preventDefault()
                    void handleLogout()
                  }}
                >
                  <LogOut className="size-4" />
                  {loggingOut ? "Logging out..." : "Logout"}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
      </header>

      <VerifyEmailBanner />

      <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-8 sm:py-10">
        <Outlet />
      </main>

      <Footer />
    </div>
  )
}
