import { Github } from "lucide-react"
import { Link } from "react-router-dom"

import { Logo } from "@/components/Logo"
import { GITHUB_URL } from "@/lib/constants"
import { useAuthStore } from "@/stores/useAuthStore"

const guestLinks = [
  { to: "/tasks", label: "Browse tasks" },
  { to: "/#how-it-works", label: "How it works" },
  { to: "/login", label: "Log in" },
  { to: "/register", label: "Sign up" },
]

const memberLinks = [
  { to: "/tasks", label: "Browse tasks" },
  { to: "/my-tasks", label: "My tasks" },
  { to: "/my-applications", label: "My applications" },
  { to: "/points", label: "Points" },
]

export function Footer() {
  const user = useAuthStore((state) => state.user)
  const links = user ? memberLinks : guestLinks
  const currentYear = new Date().getFullYear()

  return (
    <footer className="border-t border-border bg-card">
      <div className="mx-auto w-full max-w-6xl px-4 py-10">
        <div className="flex flex-col gap-8 sm:flex-row sm:items-start sm:justify-between">
          <div className="max-w-sm space-y-3">
            <Link
              to="/"
              className="inline-flex items-center rounded-lg focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
            >
              <Logo />
            </Link>
            <p className="text-sm leading-relaxed text-muted-foreground">
              A place to find teammates and finish tasks together.
            </p>
          </div>

          <nav aria-label="Footer" className="flex flex-col gap-3">
            <span className="text-sm font-medium text-foreground">Explore</span>
            <ul className="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
              {links.map((link) => (
                <li key={link.to}>
                  <Link
                    to={link.to}
                    className="text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                  >
                    {link.label}
                  </Link>
                </li>
              ))}
              {GITHUB_URL ? (
                <li>
                  <a
                    href={GITHUB_URL}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-1.5 text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                  >
                    <Github className="size-4" />
                    GitHub
                  </a>
                </li>
              ) : null}
            </ul>
          </nav>
        </div>

        <div className="mt-8 border-t border-border pt-6 text-xs text-muted-foreground">
          <span>&copy; {currentYear} TaskMate. All rights reserved.</span>
        </div>
      </div>
    </footer>
  )
}
