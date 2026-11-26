import { Github } from "lucide-react"

import { API_BASE_URL } from "@/api/client"
import { Button } from "@/components/ui/button"
import { storeSocialRedirect } from "@/lib/redirect"

function GoogleIcon() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true" className="size-4">
      <path
        fill="#4285F4"
        d="M23.49 12.27c0-.79-.07-1.54-.19-2.27H12v4.51h6.47a5.53 5.53 0 0 1-2.4 3.58v3h3.86c2.26-2.09 3.56-5.17 3.56-8.82Z"
      />
      <path
        fill="#34A853"
        d="M12 24c3.24 0 5.96-1.08 7.93-2.91l-3.86-3c-1.08.72-2.45 1.16-4.07 1.16-3.13 0-5.78-2.11-6.73-4.96H1.29v3.09A11.99 11.99 0 0 0 12 24Z"
      />
      <path
        fill="#FBBC05"
        d="M5.27 14.29a7.2 7.2 0 0 1 0-4.58V6.62H1.29a11.99 11.99 0 0 0 0 10.76l3.98-3.09Z"
      />
      <path
        fill="#EA4335"
        d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.26 2.69 1.29 6.62l3.98 3.09C6.22 6.86 8.87 4.75 12 4.75Z"
      />
    </svg>
  )
}

const providers = [
  { id: "google", label: "Google", icon: GoogleIcon },
  { id: "github", label: "GitHub", icon: Github },
]

export function SocialAuthButtons({
  redirect,
}: {
  redirect?: string | null
}) {
  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-2">
        {providers.map((provider) => (
          <Button key={provider.id} type="button" variant="outline" asChild>
            <a
              href={`${API_BASE_URL}/auth/${provider.id}/redirect`}
              onClick={() => storeSocialRedirect(redirect)}
            >
              <provider.icon />
              {provider.label}
            </a>
          </Button>
        ))}
      </div>

      <div className="flex items-center gap-3">
        <span className="h-px flex-1 bg-border" />
        <span className="text-xs text-muted-foreground">or continue with email</span>
        <span className="h-px flex-1 bg-border" />
      </div>
    </div>
  )
}
