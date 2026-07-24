# Live Server Auto Update Setup

This project now includes an automatic deploy workflow:

- .github/workflows/live-deploy.yml

It deploys to your live hosting server on every push to main or master.

## 1. Initialize git in this folder (if not already done)

Run:

```bash
git init
git add .
git commit -m "Initial commit with live deploy workflow"
```

## 2. Create a GitHub repository and push

Run:

```bash
git branch -M main
git remote add origin https://github.com/YOUR_USERNAME/YOUR_REPO.git
git push -u origin main
```

## 3. Add GitHub repository secrets

In GitHub: Settings > Secrets and variables > Actions > New repository secret

Create these secrets:

- FTP_SERVER: your hosting server hostname (example: ftp.yourdomain.com)
- FTP_USERNAME: your FTP username
- FTP_PASSWORD: your FTP password
- FTP_SERVER_DIR: target directory (example: /public_html/)

## 4. Verify deploy

Push any small change to main. The workflow will run under:

- GitHub repository > Actions > Live Server Auto Deploy

## Notes

- The workflow excludes .github, .git, node_modules, and local metadata files.
- If your host uses SFTP instead of FTP/FTPS, use an SFTP deploy action instead.

## Site Stats Setup (Traffic + Location)

This site includes:

- `analytics.php` (collects traffic and builds stats)
- `analytics-dashboard.php` (protected dashboard view)

To secure dashboard access in production, set an environment variable on your host:

- `SITE_STATS_TOKEN`: strong random token used for dashboard authentication

Open the dashboard with:

- `https://your-domain/analytics-dashboard.php?token=YOUR_TOKEN`

Important hosting requirement:

- Ensure the web server can create/write the `data/` directory and files used by analytics (`data/analytics-events.jsonl`, `data/analytics-geo-cache.json`).
