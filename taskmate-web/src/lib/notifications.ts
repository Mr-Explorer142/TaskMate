import type { AppNotification, NotificationType } from "@/types"

const FALLBACK_TITLES: Record<NotificationType, string> = {
  application_received: "New application",
  application_accepted: "Application accepted",
  application_rejected: "Application update",
  member_left: "Team member left",
  project_started: "Project started",
  project_completed: "Project completed",
  project_cancelled: "Project cancelled",
  review_received: "New review",
}

const TASK_NOTIFICATION_TYPES: NotificationType[] = [
  "application_accepted",
  "application_rejected",
  "member_left",
  "project_started",
  "project_completed",
  "project_cancelled",
]

export function notificationTitle(notification: AppNotification): string {
  return notification.title ?? FALLBACK_TITLES[notification.type]
}

export function notificationTarget(
  notification: AppNotification,
  currentUserId: number | null
): string | null {
  const taskId = notification.data.task_id

  if (notification.type === "application_received") {
    return taskId ? `/tasks/${taskId}/applicants` : "/my-tasks"
  }

  if (TASK_NOTIFICATION_TYPES.includes(notification.type)) {
    return taskId ? `/tasks/${taskId}` : "/my-tasks"
  }

  if (notification.type === "review_received") {
    return currentUserId ? `/users/${currentUserId}#reviews` : "/profile#reviews"
  }

  return null
}
