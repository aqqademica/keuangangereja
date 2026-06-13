# Walkthrough: Dashboard Analytics & Input Workflows

This document summarizes the new features implemented to enhance the Bendahara's financial analytics, public transparency, and input auditing workflow.

## 1. Bendahara Dashboard Analytics
The Bendahara and Ketua Gereja now have access to a rich, visual dashboard powered by **Chart.js** directly on their home screen.
- **Penerimaan per Bulan**: A bar chart tracking the total incoming funds (`uang_masuk`) month-over-month for the current year.
- **Penerimaan per Kategori**: A pie chart breaking down the total verified funds by category (Perpuluhan, Persembahan, Pembangunan, dll).
- **Tingkat Partisipasi**: A doughnut chart comparing the number of unique Jemaat who have donated against the total number of registered Jemaat.

## 2. Secure Public Donation Tracking
- **Anonim Token Generation**: When a Bendahara manually inputs a donation for an unregistered or anonymous person, the system automatically generates a unique **Receipt ID / Token** (e.g., `TRX-2026-ABC12`).
- **Cek Donasi Publik**: A new public-facing page (`cek_donasi.php`) was created. Non-users can access this via a prominent link on the `login.php` screen to securely check their donation status by inputting their Token.

## 3. Auditing & Two-Step Manual Input
- **Two-Step Modal Wizard**: The "Input Setoran Manual" feature has been upgraded into a clean, 2-step JavaScript interface. Step 1 allows the Bendahara to search and filter for a Jemaat dynamically, or explicitly choose "Lanjut sebagai Anonim". Step 2 reveals the input form.
- **Audit Trail (`input_by`)**: The database was migrated to track exactly *who* created a transaction. The Uang Masuk table now displays a "Di-input Oleh" column, clearly distinguishing between "Bendahara" (manual entry) and "Jemaat via App" (self-submitted).

## 4. Notifications & Security Hardening
- **Notification Bell**: A notification dropdown was added to the top navigation bar. Jemaat will now see real-time alerts when their transactions are verified or rejected by the Bendahara.
- **Role Restrictions**: 
  - The "Reset Password" verification module is now strictly restricted to the **Ketua Gereja**.
  - The "Laporan Keuangan" module remains securely restricted to only the **Ketua Gereja** and **Bendahara**.

> [!TIP]
> Log in as `bendahara@gereja.local` to view the beautiful new analytics charts on the dashboard and try out the two-step manual input wizard!
