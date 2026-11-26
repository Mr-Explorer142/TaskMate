import { lazy, Suspense } from "react"
import { BrowserRouter, Route, Routes } from "react-router-dom"

import { AuthBootstrap } from "@/components/AuthBootstrap"
import { ScrollToTop } from "@/components/ScrollToTop"
import { AppLayout } from "@/components/layout/AppLayout"
import {
  PageLoadingSkeleton,
  ProtectedRoute,
  PublicOnlyRoute,
} from "@/components/ProtectedRoute"
import { Toaster } from "@/components/ui/sonner"

const AuthCallbackPage = lazy(() =>
  import("@/pages/AuthCallback").then((module) => ({
    default: module.AuthCallbackPage,
  }))
)
const HomePage = lazy(() =>
  import("@/pages/Home").then((module) => ({ default: module.HomePage }))
)
const CreateTaskPage = lazy(() =>
  import("@/pages/CreateTask").then((module) => ({
    default: module.CreateTaskPage,
  }))
)
const EditTaskPage = lazy(() =>
  import("@/pages/EditTask").then((module) => ({
    default: module.EditTaskPage,
  }))
)
const LoginPage = lazy(() =>
  import("@/pages/Login").then((module) => ({ default: module.LoginPage }))
)
const MessagesPage = lazy(() =>
  import("@/pages/Messages").then((module) => ({
    default: module.MessagesPage,
  }))
)
const MyApplicationsPage = lazy(() =>
  import("@/pages/MyApplications").then((module) => ({
    default: module.MyApplicationsPage,
  }))
)
const MyTasksPage = lazy(() =>
  import("@/pages/MyTasks").then((module) => ({
    default: module.MyTasksPage,
  }))
)
const NotFoundPage = lazy(() =>
  import("@/pages/NotFound").then((module) => ({
    default: module.NotFoundPage,
  }))
)
const NotificationsPage = lazy(() =>
  import("@/pages/Notifications").then((module) => ({
    default: module.NotificationsPage,
  }))
)
const PointsPage = lazy(() =>
  import("@/pages/Points").then((module) => ({ default: module.PointsPage }))
)
const ProfilePage = lazy(() =>
  import("@/pages/Profile").then((module) => ({ default: module.ProfilePage }))
)
const RegisterPage = lazy(() =>
  import("@/pages/Register").then((module) => ({
    default: module.RegisterPage,
  }))
)
const TaskApplicantsPage = lazy(() =>
  import("@/pages/TaskApplicants").then((module) => ({
    default: module.TaskApplicantsPage,
  }))
)
const TaskDetailPage = lazy(() =>
  import("@/pages/TaskDetail").then((module) => ({
    default: module.TaskDetailPage,
  }))
)
const TasksPage = lazy(() =>
  import("@/pages/Tasks").then((module) => ({ default: module.TasksPage }))
)
const UserProfilePage = lazy(() =>
  import("@/pages/UserProfile").then((module) => ({
    default: module.UserProfilePage,
  }))
)
const VerifyEmailPage = lazy(() =>
  import("@/pages/VerifyEmail").then((module) => ({
    default: module.VerifyEmailPage,
  }))
)

function App() {
  return (
    <AuthBootstrap>
      <BrowserRouter>
        <ScrollToTop />
        <Suspense fallback={<PageLoadingSkeleton />}>
          <Routes>
            <Route
              path="/login"
              element={
                <PublicOnlyRoute>
                  <LoginPage />
                </PublicOnlyRoute>
              }
            />
            <Route
              path="/register"
              element={
                <PublicOnlyRoute>
                  <RegisterPage />
                </PublicOnlyRoute>
              }
            />
            <Route path="/auth/callback" element={<AuthCallbackPage />} />
            <Route path="/verify-email" element={<VerifyEmailPage />} />

            <Route element={<AppLayout />}>
              <Route path="/" element={<HomePage />} />
              <Route path="/tasks" element={<TasksPage />} />
              <Route path="/tasks/:id" element={<TaskDetailPage />} />
            </Route>

            <Route element={<ProtectedRoute />}>
              <Route element={<AppLayout />}>
                <Route path="/tasks/new" element={<CreateTaskPage />} />
                <Route path="/tasks/:id/edit" element={<EditTaskPage />} />
                <Route
                  path="/tasks/:id/applicants"
                  element={<TaskApplicantsPage />}
                />
                <Route path="/my-tasks" element={<MyTasksPage />} />
                <Route path="/my-applications" element={<MyApplicationsPage />} />
                <Route path="/notifications" element={<NotificationsPage />} />
                <Route path="/messages" element={<MessagesPage />} />
                <Route
                  path="/messages/:conversationId"
                  element={<MessagesPage />}
                />
                <Route path="/points" element={<PointsPage />} />
                <Route path="/profile" element={<ProfilePage />} />
                <Route path="/users/:id" element={<UserProfilePage />} />
              </Route>
            </Route>

            <Route path="*" element={<NotFoundPage />} />
          </Routes>
        </Suspense>
      </BrowserRouter>
      <Toaster position="top-right" richColors closeButton />
    </AuthBootstrap>
  )
}

export default App
