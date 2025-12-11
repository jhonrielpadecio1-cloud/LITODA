# 🚀 Railway Deployment Guide for LITODA

This guide will help you deploy the LITODA application (PHP + Python Face Recognition) to Railway using a MySQL database.

## Prerequisites
- A [Railway](https://railway.app/) account.
- A [GitHub](https://github.com/) account (to host this repository).

---

## Step 1: Create a MySQL Database
1.  Go to your Railway Dashboard.
2.  Click **New Project** -> **Database** -> **MySQL**.
3.  Once created, click on the MySQL service card.
4.  Go to the **Variables** tab. You will see `MYSQLHOST`, `MYSQLUSER`, `MYSQLPASSWORD`, etc. Keep this tab open.

## Step 2: Deploy the Application
1.  Push this code to your GitHub repository.
2.  In Railway, click **New** -> **GitHub Repo** -> Select your repository.
3.  Railway will detect the `Dockerfile` and start building.
4.  **Wait!** The build might fail or the app won't work yet because variables are missing.

## Step 3: Configure Environment Variables
1.  Click on your new application service card (not the database).
2.  Go to the **Variables** tab.
3.  Add the following variables (copy values from the MySQL service):

    | Variable Name | Value (Source) |
    | :--- | :--- |
    | `DB_HOST` | `${{MySQL.MYSQLHOST}}` |
    | `DB_USER` | `${{MySQL.MYSQLUSER}}` |
    | `DB_PASSWORD` | `${{MySQL.MYSQLPASSWORD}}` |
    | `DB_NAME` | `${{MySQL.MYSQLDATABASE}}` |
    | `DB_PORT` | `${{MySQL.MYSQLPORT}}` |
    | `PORT` | `8080` (Optional, Railway sets this automatically) |

    *Note: You can type `${{` in the value field to reference other services easily.*

## Step 4: Setup Persistent Storage (Important!)
Since the app stores driver photos and face data, you must create a Volume so data isn't lost when the app restarts.

1.  Click on your application service card.
2.  Go to the **Volumes** tab.
3.  Click **Add Volume**.
4.  Mount Path: `/var/www/html/uploads`
5.  Click **Add**.
    *   This ensures that all images and the `face_data.json` (which is now stored in `uploads/`) are saved permanently.

## Step 5: Run Database Migration
1.  Once the app is deployed and "Active", click on the **Service URL** (e.g., `https://your-app.up.railway.app`).
2.  You should be redirected to the Login page.
3.  **Initialize the Database**:
    *   Go to: `https://your-app.up.railway.app/database/migrate.php`
    *   You should see "Migration completed successfully".
    *   *Security Tip: After migration, you can delete `database/migrate.php` from your repo and redeploy.*

## Step 6: Verify
1.  Go back to the Login page.
2.  Log in with the default admin credentials (if any in the SQL dump) or register a new driver.
3.  The Face Recognition system is running in the background and accessible via `/py-api/`.

---

## Troubleshooting
- **Camera not working?** Ensure you are accessing the site via **HTTPS** (Railway provides this by default). Browsers block camera access on HTTP.
- **Face Recognition Error?** Check the "Deploy Logs" in Railway. It will show if the Python backend (`gunicorn`) started successfully.
- **Uploads lost?** Verify you added the Volume mounted to `/var/www/html/uploads`.
