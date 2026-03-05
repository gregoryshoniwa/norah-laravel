# Deploying to Live (cPanel)

## After You Push to GitHub

Your live site at `live.npg.co.zw` does **not** update automatically. Follow one of these methods:

---

### Method 1: cPanel UI (No terminal needed)

1. Log in to **cPanel** → **Git Version Control**
2. Open your **norah-laravel** repository
3. Go to the **Pull or Deploy** tab
4. Click **"Update from Remote"** (pulls latest from GitHub)
5. Click **"Deploy HEAD Commit"** (copies files to production)

---

### Method 2: Push directly to cPanel (Auto-deploy)

Add cPanel as a remote and push there. cPanel will auto-deploy on push.

**One-time setup** (run locally):

```bash
git remote add cpanel ssh://npgcozw@npg.co.zw:52836/home/npgcozw/public_html/live
```

**Every time you deploy** (after committing):

```bash
git push origin master      # Push to GitHub (backup)
git push cpanel master     # Push to cPanel → auto-deploys
```

Or push to both at once:
```bash
git push origin master && git push cpanel master
```

---

### Method 3: Fix "The system cannot deploy" (run once in cPanel Terminal)

If the Deploy button is greyed out:

```bash
cd /home/npgcozw/public_html/live
git checkout master
git pull origin master
git reset --hard HEAD
git clean -fd
```

Then use Method 1 (click Update from Remote → Deploy HEAD Commit).
