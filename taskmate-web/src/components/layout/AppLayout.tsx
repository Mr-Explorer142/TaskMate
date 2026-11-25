import { Suspense, useEffect, useState } from "react"
import {
  ClipboardList,
  FileCheck2,
  ListTodo,
  LogOut,
  MessageSquare,
  Plus,
  User as UserIcon,
} from "lucide-react"
import { Link, NavLink, Outlet, useNavigate } from "react-router-dom"
import { toast } from "sonner"

import { logout } from "@/api/auth"
import { CountryFlag } from "@/components/CountryFlag"
import { Footer } from "@/components/layout/Footer"
import { Logo } from "@/components/Logo"
import { NotificationBell } from "@/components/notifications/NotificationBell"
import { PageLoadingSkeleton } from "@/components/ProtectedRoute"
import { PointsBadge } from "@/components/points/PointsBadge"
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
import { usePolling } from "@/hooks/usePolling"
import { getInitials } from "@/lib/utils"
import { useAuthStore } from "@/stores/useAuthStore"
import { useChatStore } from "@/stores/useChatStore"
import { useNotificationStore } from "@/stores/useNotificationStore"
import { usePointsStore } from "@/stores/usePointsStore"

const navigation = [
  { to: "/tasks", label: "Tasks", shortLabel: "Tasks", icon: ListTodo },
  {
    to: "/my-tasks",
    label: "My Tasks",
    shortLabel: "My Tasks",
    icon: ClipboardList,
  },
  {
    to: "/my-applications",
    label: "My Applications",
    shortLabel: "Applications",
    icon: FileCheck2,
  },
  {
    to: "/messages",
    label: "Messages",
    shortLabel: "Messages",
    icon: MessageSquare,
  },
]

function PrimaryNavigation({
  unreadCount,
  mobile = false,
}: {
  unreadCount: number
  mobile?: boolean
}) {
  return (
    <nav
      className={
        mobile
          ? "flex items-center justify-between gap-1 border-t border-border py-1 sm:hidden"
          : "hidden items-center gap-1 sm:flex"
      }
    >
      {navigation.map((item) => (
        <NavLink
          key={item.to}
          to={item.to}
          className={({ isActive }) =>
            [
              "flex whitespace-nowrap rounded-lg font-medium transition-colors",
              mobile
                ? "flex-1 flex-col items-center justify-center gap-0.5 px-1 py-1.5 text-[11px]"
                : "items-center gap-1.5 px-2.5 py-1.5 text-sm",
              isActive
                ? "bg-secondary text-secondary-foreground"
                : "text-muted-foreground hover:bg-muted hover:text-foreground",
            ].join(" ")
          }
        >
          <span className={mobile ? "relative inline-flex" : "inline-flex"}>
            <item.icon className="size-4" />
            {item.to === "/messages" && unreadCount > 0 ? (
              <span
                className={
                  mobile
                    ? "absolute -right-2 -top-1.5 flex h-3.5 min-w-3.5 items-center justify-center rounded-full bg-primary px-0.5 text-[9px] font-semibold text-primary-foreground"
                    : "ml-0.5 flex min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold text-primary-foreground"
                }
              >
                {unreadCount > 99 ? "99+" : unreadCount}
              </span>
            ) : null}
          </span>
          {mobile ? (
            <span>{item.shortLabel}</span>
          ) : (
            <span className="hidden xl:inline">{item.label}</span>
          )}
        </NavLink>
      ))}
    </nav>
  )
}

export function AppLayout() {
  const user = useAuthStore((state) => state.user)
  const clearAuth = useAuthStore((state) => state.clearAuth)
  const unreadCount = useChatStore((state) => state.unreadCount)
  const fetchUnreadCount = useChatStore((state) => state.fetchUnreadCount)
  const resetChat = useChatStore((state) => state.reset)
  const resetNotifications = useNotificationStore((state) => state.reset)
  const resetPoints = usePointsStore((state) => state.reset)
  const navigate = useNavigate()
  const [loggingOut, setLoggingOut] = useState(false)

  useEffect(() => {
    void fetchUnreadCount()
  }, [fetchUnreadCount])

  usePolling(() => fetchUnreadCount(), 15_000)

  async function handleLogout() {
    setLoggingOut(true)

    try {
      await logout()
    } catch {
      // The local session is cleared even if the token was already invalid.
    } finally {
      clearAuth()
      resetChat()
      resetNotifications()
      resetPoints()
      setLoggingOut(false)
      toast.success("You have been logged out.")
      navigate("/login", { replace: true })
    }
  }

  return (
    <div className="flex min-h-screen flex-col bg-background">
      <header className="sticky top-0 z-40 border-b border-border bg-card">
        <div className="mx-auto w-full max-w-5xl px-4">
          <div className="flex h-14 items-center justify-between gap-3 sm:h-16">
            <Link to="/" className="flex items-center">
              <Logo height="h-6 sm:h-8" />
            </Link>

            <PrimaryNavigation unreadCount={unreadCount} />

            <div className="flex items-center gap-1">
              <Button asChild size="sm" className="mr-1 hidden sm:inline-flex">
                <Link to="/tasks/new" aria-label="Post a task">
                  <Plus className="size-4" />
                  <span className="hidden sm:inline">Post a task</span>
                </Link>
              </Button>

              <PointsBadge />

              <NotificationBell />

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
                  <DropdownMenuItem asChild className="sm:hidden">
                    <Link to="/tasks/new">
                      <Plus className="size-4" />
                      Post a task
                    </Link>
                  </DropdownMenuItem>
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

          <PrimaryNavigation unreadCount={unreadCount} mobile />
        </div>
      </header>

      <VerifyEmailBanner />

      <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-8 sm:py-10">
        <Suspense fallback={<PageLoadingSkeleton />}>
          <Outlet />
        </Suspense>
      </main>

      <Footer />
    </div>
  )
}
