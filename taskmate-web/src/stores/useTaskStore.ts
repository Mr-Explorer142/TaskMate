import axios from "axios"
import { create } from "zustand"

import { getApiErrorMessage } from "@/api/client"
import {
  cancelTask as cancelTaskRequest,
  completeTask as completeTaskRequest,
  createTask as createTaskRequest,
  deleteTask as deleteTaskRequest,
  listTasks,
  myTasks as myTasksRequest,
  startTask as startTaskRequest,
  updateTask as updateTaskRequest,
} from "@/api/tasks"
import type {
  Paginated,
  PaginationMeta,
  Task,
  TaskFilters,
  TaskPayload,
} from "@/types"

interface TaskState {
  tasks: Task[]
  meta: PaginationMeta | null
  loading: boolean
  error: string | null
  fetchTasks: (filters?: TaskFilters) => Promise<void>
  fetchMyTasks: (filters?: TaskFilters) => Promise<void>
  createTask: (payload: TaskPayload) => Promise<Task>
  updateTask: (id: number, payload: Partial<TaskPayload>) => Promise<Task>
  cancelTask: (id: number) => Promise<Task>
  completeTask: (id: number) => Promise<Task>
  startTask: (id: number) => Promise<Task>
  removeTask: (id: number) => Promise<void>
}

let activeController: AbortController | null = null
let activeRequest = 0

export const useTaskStore = create<TaskState>((set) => {
  async function load(
    fetcher: (filters: TaskFilters, signal: AbortSignal) => Promise<Paginated<Task>>,
    filters: TaskFilters
  ) {
    activeController?.abort()
    activeController = new AbortController()
    const request = ++activeRequest

    set({ loading: true, error: null })

    try {
      const page = await fetcher(filters, activeController.signal)

      if (request !== activeRequest) {
        return
      }

      set({
        tasks: page.data,
        meta: page.meta,
        loading: false,
        error: null,
      })
    } catch (error) {
      if (axios.isCancel(error) || request !== activeRequest) {
        return
      }

      set({
        loading: false,
        error: getApiErrorMessage(error),
      })
    }
  }

  function replaceTask(task: Task) {
    set((state) => ({
      tasks: state.tasks.map((item) => (item.id === task.id ? task : item)),
    }))
  }

  return {
    tasks: [],
    meta: null,
    loading: false,
    error: null,

    fetchTasks: (filters = {}) => load(listTasks, filters),
    fetchMyTasks: (filters = {}) => load(myTasksRequest, filters),

    async createTask(payload) {
      const task = await createTaskRequest(payload)

      set((state) => ({
        tasks: [task, ...state.tasks],
        meta: state.meta
          ? { ...state.meta, total: state.meta.total + 1 }
          : state.meta,
      }))

      return task
    },

    async updateTask(id, payload) {
      const task = await updateTaskRequest(id, payload)
      replaceTask(task)
      return task
    },

    async cancelTask(id) {
      const task = await cancelTaskRequest(id)
      replaceTask(task)
      return task
    },

    async completeTask(id) {
      const task = await completeTaskRequest(id)
      replaceTask(task)
      return task
    },

    async startTask(id) {
      const task = await startTaskRequest(id)
      replaceTask(task)
      return task
    },

    async removeTask(id) {
      await deleteTaskRequest(id)

      set((state) => ({
        tasks: state.tasks.filter((item) => item.id !== id),
        meta: state.meta
          ? { ...state.meta, total: Math.max(0, state.meta.total - 1) }
          : state.meta,
      }))
    },
  }
})
