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

export type TaskSort = "-created_at" | "-reward" | "deadline" | "team_size"

export interface TaskUploader {
  id: number
  name: string
  avatar: string | null
  average_rating: number | null
}

export interface Task {
  id: number
  title: string
  description: string
  team_size: number
  type: TaskType
  reward: string
  status: TaskStatus
  started_at: string | null
  deadline: string | null
  skills: string[]
  created_at: string
  accepted_count: number
  open_slots: number
  is_full: boolean
  can_start: boolean
  can_leave: boolean
  applications_count?: number
  pending_count?: number
  uploader?: TaskUploader
  is_owner: boolean
  my_application_id: number | null
  my_application_status: ApplicationStatus | null
}

export interface TaskFilters {
  search?: string
  type?: TaskType
  status?: TaskStatus
  skill?: string
  min_reward?: number
  max_reward?: number
  has_open_slots?: boolean
  uploader_id?: number
  sort?: TaskSort
  page?: number
  per_page?: number
}

export interface TaskPayload {
  title: string
  description: string
  team_size: number
  type: TaskType
  reward: number
  deadline: string | null
  skills: string[]
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export interface PaginationLinks {
  first: string | null
  last: string | null
  prev: string | null
  next: string | null
}

export interface Paginated<T> {
  data: T[]
  links: PaginationLinks
  meta: PaginationMeta
}

export interface ApplicationApplicant {
  id: number
  name: string
  avatar: string | null
  bio: string | null
  skills: string[]
  average_rating: number | null
  has_cv: boolean
  profile_links: ProfileLink[]
}

export interface ApplicationTaskSummary {
  id: number
  title: string
  type: TaskType
  reward: string
  status: TaskStatus
  team_size: number
  accepted_count: number
}

export interface Application {
  id: number
  status: ApplicationStatus
  message: string | null
  created_at: string
  accepted_at: string | null
  left_at: string | null
  applicant?: ApplicationApplicant
  task?: ApplicationTaskSummary
}

export interface TeamMember {
  id: number
  name: string
  avatar: string | null
  bio: string | null
  skills: string[]
  average_rating: number | null
  accepted_at: string
}

export interface TeamMeta {
  team_size: number
  accepted_count: number
  open_slots: number
}

export interface Team {
  members: TeamMember[]
  meta: TeamMeta
}

export interface ApiEnvelope<T> {
  data: T
  message: string
}

export interface ApiErrorBody {
  message: string
  errors?: Record<string, string[]>
}
