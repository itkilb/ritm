import { Navigate, Route, Routes } from "react-router-dom";

import {
  GuestOnly,
  RequireAuth,
  RequireCompletedProfile,
} from "@/components/require-auth";
import { DashboardPage } from "@/pages/dashboard-page";
import { LoginPage } from "@/pages/login-page";
import { NotFoundPage } from "@/pages/not-found-page";
import { OnboardingPage } from "@/pages/onboarding-page";
import { ProgressPage } from "@/pages/progress-page";
import { RegisterPage } from "@/pages/register-page";
import { WorkoutsPage } from "@/pages/workouts-page";

export default function App() {
  return (
    <Routes>
      <Route index element={<Navigate to="/dashboard" replace />} />
      <Route
        path="register"
        element={
          <GuestOnly>
            <RegisterPage />
          </GuestOnly>
        }
      />
      <Route
        path="login"
        element={
          <GuestOnly>
            <LoginPage />
          </GuestOnly>
        }
      />
      <Route
        path="onboarding"
        element={
          <RequireAuth>
            <OnboardingPage />
          </RequireAuth>
        }
      />
      <Route
        path="dashboard"
        element={
          <RequireCompletedProfile>
            <DashboardPage />
          </RequireCompletedProfile>
        }
      />
      <Route
        path="workouts"
        element={
          <RequireCompletedProfile>
            <WorkoutsPage />
          </RequireCompletedProfile>
        }
      />
      <Route
        path="progress"
        element={
          <RequireCompletedProfile>
            <ProgressPage />
          </RequireCompletedProfile>
        }
      />
      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  );
}
