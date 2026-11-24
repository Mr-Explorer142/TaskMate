export type TaskType = "paid" | "points"

export type TaskStatus =
  | "open"
  | "full"
  | "in_progress"
  | "completed"
  | "cancelled"

export type ApplicationStatus = "pending" | "accepted" | "rejected" | "left"

export type LinkPlatform =
  | "github"
  | "linkedin"
  | "portfolio"
  | "behance"
  | "dribbble"
  | "twitter"
  | "youtube"
  | "website"
  | "other"

export interface ProfileLink {
  id: number
  platform: LinkPlatform
  label: string | null
  url: string
  sort_order: number
  created_at?: string
  updated_at?: string
}

export interface User {
  id: number
  name: string
  email: string
  email_verified: boolean
  country: string | null
  bio: string | null
  skills: string[]
  avatar: string | null
  has_cv: boolean
  cv_original_name: string | null
  cv_uploaded_at: string | null
  average_rating: number | null
  points_balance: number
  profile_links: ProfileLink[]
  created_at: string
  updated_at: string
}

export interface PublicUser {
  id: number
  name: string
  country: string | null
  bio: string | null
  skills: string[]
  avatar: string | null
  average_rating: number | null
  has_cv: boolean
  profile_links: ProfileLink[]
  created_at: string
}

export interface Task {
  id: number
  uploader_id: number
  title: string
  description: string
  team_size: number
  type: TaskType
  reward: string
  status: TaskStatus
  deadline: string | null
  accepted_count: number
  open_slots: number
  is_full: boolean
  uploader?: PublicUser
  created_at: string
  updated_at: string
}

export interface Application {
  id: number
  task_id: number
  user_id: number
  message: string | null
  status: ApplicationStatus
  accepted_at: string | null
  left_at: string | null
  created_at: string
  updated_at: string
}

export interface ApiEnvelope<T> {
  data: T
  message: string
}

export interface ApiErrorBody {
  message: string
  errors?: Record<string, string[]>
}
