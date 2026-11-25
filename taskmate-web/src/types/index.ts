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
  reviews_count: number
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
  reviews_count: number
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
  reviews_count: number
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
  completed_at: string | null
  deadline: string | null
  skills: string[]
  created_at: string
  accepted_count: number
  open_slots: number
  is_full: boolean
  can_start: boolean
  can_complete: boolean
  can_leave: boolean
  is_participant: boolean
  pending_reviews_count: number
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
  reviews_count: number
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
  pending_reviews_count: number
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
  reviews_count: number
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

export interface PendingReviewee {
  id: number
  name: string
  avatar: string | null
}

export interface ReviewSummary {
  average_rating: number | null
  reviews_count: number
  distribution: Record<1 | 2 | 3 | 4 | 5, number>
}

export interface ReviewReviewer {
  id: number
  name: string
  avatar: string | null
}

export interface ReviewTask {
  id: number
  title: string
}

export interface Review {
  id: number
  reviewee_id: number
  rating: number
  comment: string | null
  created_at: string | null
  reviewer: ReviewReviewer
  task: ReviewTask
}

export type ReviewList = Paginated<Review> & { meta: PaginationMeta & ReviewSummary }

export interface ReviewPayload {
  reviewee_id: number
  rating: number
  comment: string | null
}

export type ReviewFormPayload = Omit<ReviewPayload, "reviewee_id">

export interface ConversationOtherUser {
  id: number
  name: string
  avatar: string | null
}

export interface ConversationTask {
  id: number
  title: string
}

export interface ConversationLastMessage {
  id: number
  sender_id: number
  body: string
  created_at: string | null
}

export interface Conversation {
  id: number
  task_id: number
  task: ConversationTask | null
  other_user: ConversationOtherUser | null
  last_message: ConversationLastMessage | null
  unread_count: number
  created_at: string | null
  updated_at: string | null
}

export interface Message {
  id: number
  conversation_id: number
  sender_id: number
  body: string
  created_at: string | null
  read_at: string | null
  /** Local-only flag for optimistic messages. */
  pending?: boolean
}

export type PointTransactionType = "bonus" | "escrow" | "release" | "refund"

export interface PointTransactionTask {
  id: number
  title: string
}

export interface PointTransaction {
  id: number
  amount: number
  type: PointTransactionType
  note: string | null
  task: PointTransactionTask | null
  created_at: string | null
}

export interface PointsSummary {
  balance: number
  escrowed: number
}

export interface ApiEnvelope<T> {
  data: T
  message: string
}

export interface ApiErrorBody {
  message: string
  errors?: Record<string, string[]>
}
