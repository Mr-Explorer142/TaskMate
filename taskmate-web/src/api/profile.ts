import { api } from "@/api/client"
import type {
  ApiEnvelope,
  LinkPlatform,
  ProfileLink,
  PublicUser,
  User,
} from "@/types"

export interface UpdateProfilePayload {
  name?: string
  country?: string | null
  bio?: string | null
  skills?: string[]
}

export interface LinkPayload {
  url: string
  label?: string | null
  platform?: LinkPlatform | null
}

export interface CvLink {
  url: string
  expires_in: number
}

export async function updateProfile(payload: UpdateProfilePayload): Promise<User> {
  const { data } = await api.put<ApiEnvelope<User>>("/profile", payload)
  return data.data
}

export async function getUser(id: number): Promise<PublicUser> {
  const { data } = await api.get<ApiEnvelope<PublicUser>>(`/users/${id}`)
  return data.data
}

export async function uploadAvatar(
  file: File,
  onProgress?: (percent: number) => void
): Promise<User> {
  const formData = new FormData()
  formData.append("avatar", file)

  const { data } = await api.post<ApiEnvelope<User>>("/profile/avatar", formData, {
    onUploadProgress: (event) => {
      if (event.total) {
        onProgress?.(Math.round((event.loaded * 100) / event.total))
      }
    },
  })

  return data.data
}

export async function deleteAvatar(): Promise<User> {
  const { data } = await api.delete<ApiEnvelope<User>>("/profile/avatar")
  return data.data
}

export async function uploadCv(
  file: File,
  onProgress?: (percent: number) => void
): Promise<User> {
  const formData = new FormData()
  formData.append("cv", file)

  const { data } = await api.post<ApiEnvelope<User>>("/profile/cv", formData, {
    onUploadProgress: (event) => {
      if (event.total) {
        onProgress?.(Math.round((event.loaded * 100) / event.total))
      }
    },
  })

  return data.data
}

export async function deleteCv(): Promise<User> {
  const { data } = await api.delete<ApiEnvelope<User>>("/profile/cv")
  return data.data
}

export async function getCvLink(userId: number): Promise<CvLink> {
  const { data } = await api.get<ApiEnvelope<CvLink>>(`/users/${userId}/cv`)
  return data.data
}

export async function listLinks(): Promise<ProfileLink[]> {
  const { data } = await api.get<ApiEnvelope<ProfileLink[]>>("/profile/links")
  return data.data
}

export async function createLink(payload: LinkPayload): Promise<ProfileLink> {
  const { data } = await api.post<ApiEnvelope<ProfileLink>>("/profile/links", payload)
  return data.data
}

export async function updateLink(
  id: number,
  payload: Partial<LinkPayload>
): Promise<ProfileLink> {
  const { data } = await api.put<ApiEnvelope<ProfileLink>>(
    `/profile/links/${id}`,
    payload
  )
  return data.data
}

export async function deleteLink(id: number): Promise<void> {
  await api.delete(`/profile/links/${id}`)
}

export async function reorderLinks(ids: number[]): Promise<ProfileLink[]> {
  const { data } = await api.patch<ApiEnvelope<ProfileLink[]>>(
    "/profile/links/reorder",
    { ids }
  )
  return data.data
}
