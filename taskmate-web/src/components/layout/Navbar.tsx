import { useEffect, useState } from "react"
import { Menu, MessageSquare, Plus } from "lucide-react"
import { Link, NavLink, useLocation, useNavigate } from "react-router-dom"
import { toast } from "sonner"

import { logout } from "@/api/auth"
import { MobileMenu } from "@/components/layout/MobileMenu"
import { UserMenu } from "@/components/layout/UserMenu"
import { Logo } from "@/components/Logo"
import { NotificationBell } from "@/components/notifications/NotificationBell"
import { PointsBadge } from "@/components/points/PointsBadge"
import { ThemeToggle } from "@/components/ThemeToggle"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { useAuthStore } from "@/stores/useAuthStore"
import { useChatStore } from "@/stores/useChatStore"
import { useNotificationStore } from "@/stores/useNotificationStore"
import { usePointsStore } from "@/stores/usePointsStore"

const memberLinks = [
  { to: "/tasks", label: "Tasks" },
  { to: "/my-tasks", label: "My tasks" },
  { to: "/my-applications", label: "My applications" },
  { to: "/messages", label: "Messages" },
]

const guestLinks = [
  { to: "/tasks", label: "Browse tasks" },
  { to: "/#how-it-works", label: "How it works" },
]

export function Navbar() {
  const user = useAuthStore((state) => state.user)
  const isHydrating = useAuthStore((state) => state.isHydrating)
  const clearAuth = useAuthStore((state) => state.clearAuth)
  const unreadCount = useChatStore((state) => state.unreadCount)
  const resetChat = useChatStore((state) => state.reset)
  const resetNotifications = useNotificationStore((state) => state.reset)
  const resetPoints = usePointsStore((state) => state.reset)
  const [menuOpen, setMenuOpen] = useState(false)
  const [loggingOut, setLoggingOut] = useState(false)
  const location = useLocation()
  const navigate = useNavigate()

  useEffect(() => {
    setMenuOpen(false)
  }, [location.pathname, location.search, location.hash])

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
      setMenuOpen(false)
      toast.success("You have been logged out.")
      navigate("/", { replace: true })
    }
  }

  return (
    <header className="sticky top-0 z-40 border-b border-border bg-card">
      <div className="mx-auto w-full max-w-6xl px-4">
        <div className="flex h-16 items-center justify-between gap-3">
          <Link
            to="/"
            className="flex shrink-0 items-center rounded-lg focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
            aria-label="TaskMate home"
          >
            <Logo height="h-7 sm:h-8" />
          </Link>

          {!isHydrating ? (
            <nav
              aria-label="Main"
              className="hidden items-center gap-1 lg:flex"
            >
              {user
                ? memberLinks.map((link) => (
                    <NavLink
                      key={link.to}
                      to={link.to}
                      className={({ isActive }) =>
                        [
                          "relative flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-ring/50",
                          isActive
                            ? "bg-secondary text-primary"
                            : "text-muted-foreground hover:bg-muted hover:text-foreground",
                        ].join(" ")
                      }
                    >
                      {link.to === "/messages" ? (
                        <MessageSquare className="size-4" />
                      ) : null}
                      {link.label}
                      {link.to === "/messages" && unreadCount > 0 ? (
                        <span className="flex min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold text-primary-foreground">
                          {unreadCount > 99 ? "99+" : unreadCount}
                        </span>
                      ) : null}
                    </NavLink>
                  ))
                : guestLinks.map((link) =>
                    link.to.startsWith("/#") ? (
                      <Link
                        key={link.to}
                        to={link.to}
                        className="rounded-lg px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                      >
                        {link.label}
                      </Link>
                    ) : (
                      <NavLink
                        key={link.to}
                        to={link.to}
                        className={({ isActive }) =>
                          [
                            "rounded-lg px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-ring/50",
                            isActive
                              ? "bg-secondary text-primary"
                              : "text-muted-foreground hover:bg-muted hover:text-foreground",
                          ].join(" ")
                        }
                      >
                        {link.label}
                      </NavLink>
                    )
                  )}
            </nav>
          ) : null}

          <div className="flex items-center gap-1">
            {isHydrating ? (
              <div className="flex items-center gap-2" aria-hidden="true">
                <Skeleton className="h-8 w-16 rounded-lg" />
                <Skeleton className="h-8 w-20 rounded-lg" />
              </div>
            ) : user ? (
              <>
                <div className="hidden lg:block">
                  <ThemeToggle />
                </div>

                <Button asChild size="sm" className="hidden lg:inline-flex">
                  <Link to="/tasks/new">
                    <Plus className="size-4" />
                    Post a task
                  </Link>
                </Button>

                <div className="hidden lg:block">
                  <NotificationBell />
                </div>

                <div className="hidden lg:block">
                  <PointsBadge />
                </div>

                <div className="hidden lg:block">
                  <UserMenu
                    user={user}
                    loggingOut={loggingOut}
                    onLogout={() => void handleLogout()}
                  />
                </div>

                <div className="lg:hidden">
                  <NotificationBell />
                </div>
              </>
            ) : (
              <div className="hidden items-center gap-1 lg:flex">
                <ThemeToggle />
                <Button asChild variant="ghost" size="sm">
                  <Link to="/login">Log in</Link>
                </Button>
                <Button asChild size="sm">
                  <Link to="/register">Sign up</Link>
                </Button>
              </div>
            )}

            <Button
              type="button"
              variant="ghost"
              size="icon"
              className="lg:hidden"
              aria-label="Open menu"
              onClick={() => setMenuOpen(true)}
            >
              <Menu className="size-5" />
            </Button>
          </div>
        </div>
      </div>

      <MobileMenu
        open={menuOpen}
        onOpenChange={setMenuOpen}
        user={user}
        unreadCount={unreadCount}
        loggingOut={loggingOut}
        onLogout={() => void handleLogout()}
      />
    </header>
  )
}
