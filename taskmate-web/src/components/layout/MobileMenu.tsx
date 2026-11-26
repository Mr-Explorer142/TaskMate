import { Coins, LogOut, Plus } from "lucide-react"
import { Link } from "react-router-dom"

import { Button } from "@/components/ui/button"
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet"
import { formatPoints } from "@/lib/format"
import type { User } from "@/types"

const guestLinks = [
  { to: "/tasks", label: "Browse tasks" },
  { to: "/#how-it-works", label: "How it works" },
]

const memberLinks = [
  { to: "/tasks", label: "Tasks" },
  { to: "/my-tasks", label: "My tasks" },
  { to: "/my-applications", label: "My applications" },
  { to: "/messages", label: "Messages" },
]

export function MobileMenu({
  open,
  onOpenChange,
  user,
  unreadCount,
  loggingOut,
  onLogout,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  user: User | null
  unreadCount: number
  loggingOut: boolean
  onLogout: () => void
}) {
  function close() {
    onOpenChange(false)
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-72 gap-0">
        <SheetHeader>
          <SheetTitle>Menu</SheetTitle>
        </SheetHeader>

        <div className="flex flex-1 flex-col gap-1 overflow-y-auto px-4 pb-6">
          {user ? (
            <>
              <nav aria-label="Mobile" className="flex flex-col gap-1">
                {memberLinks.map((link) => (
                  <Link
                    key={link.to}
                    to={link.to}
                    onClick={close}
                    className="flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-medium text-foreground transition-colors hover:bg-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                  >
                    <span>{link.label}</span>
                    {link.to === "/messages" && unreadCount > 0 ? (
                      <span className="flex min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-[10px] font-semibold text-primary-foreground">
                        {unreadCount > 99 ? "99+" : unreadCount}
                      </span>
                    ) : null}
                  </Link>
                ))}
                <Link
                  to="/notifications"
                  onClick={close}
                  className="rounded-lg px-3 py-2.5 text-sm font-medium text-foreground transition-colors hover:bg-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                >
                  Notifications
                </Link>
              </nav>

              <Button asChild className="mt-3 w-full">
                <Link to="/tasks/new" onClick={close}>
                  <Plus className="size-4" />
                  Post a task
                </Link>
              </Button>

              <Link
                to="/points"
                onClick={close}
                className="mt-2 flex items-center gap-2 rounded-lg border border-border bg-secondary/60 px-3 py-2.5 text-sm font-medium text-secondary-foreground"
              >
                <Coins className="size-4" />
                {formatPoints(user.points_balance)} pts
              </Link>

              <Button
                type="button"
                variant="ghost"
                className="mt-2 w-full justify-start text-destructive hover:text-destructive"
                disabled={loggingOut}
                onClick={onLogout}
              >
                <LogOut className="size-4" />
                {loggingOut ? "Logging out..." : "Logout"}
              </Button>
            </>
          ) : (
            <>
              <nav aria-label="Mobile" className="flex flex-col gap-1">
                {guestLinks.map((link) => (
                  <Link
                    key={link.to}
                    to={link.to}
                    onClick={close}
                    className="rounded-lg px-3 py-2.5 text-sm font-medium text-foreground transition-colors hover:bg-muted focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                  >
                    {link.label}
                  </Link>
                ))}
              </nav>

              <div className="mt-3 flex flex-col gap-2 border-t border-border pt-4">
                <Button asChild variant="ghost" className="w-full">
                  <Link to="/login" onClick={close}>
                    Log in
                  </Link>
                </Button>
                <Button asChild className="w-full">
                  <Link to="/register" onClick={close}>
                    Sign up
                  </Link>
                </Button>
              </div>
            </>
          )}
        </div>
      </SheetContent>
    </Sheet>
  )
}
