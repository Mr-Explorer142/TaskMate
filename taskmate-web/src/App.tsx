import { BrowserRouter, Route, Routes } from "react-router-dom"

import { AuthBootstrap } from "@/components/AuthBootstrap"
import { AppLayout } from "@/components/layout/AppLayout"
import { ProtectedRoute, PublicOnlyRoute } from "@/components/ProtectedRoute"
import { Toaster } from "@/components/ui/sonner"
import { AuthCallbackPage } from "@/pages/AuthCallback"
import { HomePage } from "@/pages/Home"
import { LoginPage } from "@/pages/Login"
import { NotFoundPage } from "@/pages/NotFound"
import { ProfilePage } from "@/pages/Profile"
import { RegisterPage } from "@/pages/Register"
import { UserProfilePage } from "@/pages/UserProfile"
import { VerifyEmailPage } from "@/pages/VerifyEmail"

function App() {
  return (
    <AuthBootstrap>
      <BrowserRouter>
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

          <Route element={<ProtectedRoute />}>
            <Route element={<AppLayout />}>
              <Route path="/" element={<HomePage />} />
              <Route path="/profile" element={<ProfilePage />} />
              <Route path="/users/:id" element={<UserProfilePage />} />
            </Route>
          </Route>

          <Route path="*" element={<NotFoundPage />} />
        </Routes>
      </BrowserRouter>
      <Toaster position="top-right" richColors closeButton />
    </AuthBootstrap>
  )
}

export default App
