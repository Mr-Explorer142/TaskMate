import axios from "axios"
import { create } from "zustand"

import { getApiErrorMessage } from "@/api/client"
import {
  acceptApplication as acceptApplicationRequest,
  applyToTask as applyToTaskRequest,
  leaveApplication as leaveApplicationRequest,
  listMyApplications,
  listTaskApplications,
  rejectApplication as rejectApplicationRequest,
  withdrawApplication as withdrawApplicationRequest,
  type ApplicationListFilters,
} from "@/api/applications"
import type { Application, PaginationMeta } from "@/types"

interface ApplicationState {
  myApplications: Application[]
  myMeta: PaginationMeta | null
  myLoading: boolean
  myError: string | null
  taskApplications: Application[]
  taskMeta: PaginationMeta | null
  taskLoading: boolean
  taskError: string | null
  fetchMyApplications: (filters?: ApplicationListFilters) => Promise<void>
  fetchTaskApplications: (
    taskId: number,
    filters?: ApplicationListFilters
  ) => Promise<void>
  apply: (taskId: number, message?: string | null) => Promise<Application>
  withdraw: (id: number) => Promise<void>
  accept: (id: number) => Promise<Application>
  reject: (id: number) => Promise<Application>
  leave: (id: number) => Promise<Application>
  resetTaskApplications: () => void
}

let myController: AbortController | null = null
let myRequest = 0
let taskController: AbortController | null = null
let taskRequest = 0

export const useApplicationStore = create<ApplicationState>((set) => {
  async function loadMyApplications(filters: ApplicationListFilters) {
    myController?.abort()
    myController = new AbortController()
    const request = ++myRequest

    set({ myLoading: true, myError: null })

    try {
      const page = await listMyApplications(filters, myController.signal)

      if (request !== myRequest) {
        return
      }

      set({
        myApplications: page.data,
        myMeta: page.meta,
        myLoading: false,
        myError: null,
      })
    } catch (error) {
      if (axios.isCancel(error) || request !== myRequest) {
        return
      }

      set({
        myLoading: false,
        myError: getApiErrorMessage(error),
      })
    }
  }

  async function loadTaskApplications(
    taskId: number,
    filters: ApplicationListFilters
  ) {
    taskController?.abort()
    taskController = new AbortController()
    const request = ++taskRequest

    set({ taskLoading: true, taskError: null })

    try {
      const page = await listTaskApplications(
        taskId,
        filters,
        taskController.signal
      )

      if (request !== taskRequest) {
        return
      }

      set({
        taskApplications: page.data,
        taskMeta: page.meta,
        taskLoading: false,
        taskError: null,
      })
    } catch (error) {
      if (axios.isCancel(error) || request !== taskRequest) {
        return
      }

      set({
        taskLoading: false,
        taskError: getApiErrorMessage(error),
      })
    }
  }

  return {
    myApplications: [],
    myMeta: null,
    myLoading: false,
    myError: null,
    taskApplications: [],
    taskMeta: null,
    taskLoading: false,
    taskError: null,

    fetchMyApplications: (filters = {}) => loadMyApplications(filters),
    fetchTaskApplications: (taskId, filters = {}) =>
      loadTaskApplications(taskId, filters),

    apply: (taskId, message) => applyToTaskRequest(taskId, message),
    withdraw: (id) => withdrawApplicationRequest(id),
    accept: (id) => acceptApplicationRequest(id),
    reject: (id) => rejectApplicationRequest(id),
    leave: (id) => leaveApplicationRequest(id),

    resetTaskApplications() {
      taskController?.abort()
      taskRequest++

      set({
        taskApplications: [],
        taskMeta: null,
        taskLoading: false,
        taskError: null,
      })
    },
  }
})
