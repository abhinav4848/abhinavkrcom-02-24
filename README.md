# abhinavkr.com homepage

Source files for the homepage at <https://abhinavkr.com/>.

This repository contains only the homepage:

```text
index.htm
css/
image/
```

The hosted root `index.php` loads `02_24/index.htm`. WordPress and the other applications in `public_html` are separate from this repository.

## Working copy

Create a clean local checkout with:

```sh
git clone https://github.com/abhinav4848/abhinavkrcom-02-24.git abhinavkr-homepage
cd abhinavkr-homepage
```

The current baseline was updated with the homepage files from the existing website. The old FTP-transferred `.git` directory was not copied. The existing GitHub history is preserved.

## Save changes to GitHub

Run these commands from the clean checkout:

```sh
git status
git diff
git add index.htm css image
git commit -m "Update homepage"
git push origin main
```

When intentionally changing supporting files such as this README or `.gitignore`, stage those named files too. Review the changes before committing. Make future edits in this checkout rather than the old FTP copy.

Git uses the local `.git` directory to manage history. It does not upload that directory as website content when you push commits to GitHub.

## Website publishing

The **Publish homepage** GitHub Actions workflow connects to the Linux hosting account over SSH. Once automatic publishing is enabled, pushes to `main` that change the homepage or its workflow publish the new files.

Only `index.htm`, `css/`, and `image/` are transferred into the configured `02_24` directory. Assets transfer first, then HTML. The workflow does not upload `.git`, root routing files, WordPress, or other applications. It does not delete older assets.

The new hosting account can be previewed at:

<http://sh00003.bigrock.tempwebhost.net/~sa1792ca/>

Homepage asset paths are relative to the entry page, so they work under this temporary URL prefix and at the eventual domain root. `hosting/index.php` is the small root entry point installed once at `public_html/index.php`; routine deployment only updates the files in `02_24`.

Publishing to this new account does not change DNS. The current domain remains on its existing hosting until a separate migration is arranged. Other applications, including WordPress, need their own migration before a whole-domain switch.

### Repository settings

In **Settings → Secrets and variables → Actions**, configure:

| Kind | Name | Purpose |
| --- | --- | --- |
| Variable | `HOMEPAGE_SSH_HOST` | Verified hosting IP or hostname |
| Variable | `HOMEPAGE_SSH_PORT` | Hosting SSH port |
| Variable | `HOMEPAGE_SSH_USER` | Hosting account username |
| Variable | `HOMEPAGE_DIRECTORY` | Absolute publishing directory, ending in `/02_24` |
| Variable | `HOMEPAGE_AUTO_DEPLOY` | `true` enables publishing on pushes; `false` pauses it |
| Secret | `HOMEPAGE_SSH_KEY` | The dedicated deployment private key |
| Secret | `HOMEPAGE_KNOWN_HOSTS` | The pinned SSH host key for this server |

The public half of the deployment key is authorized in cPanel. The private key is stored in GitHub Actions Secrets and is never committed. Local setup files are in the ignored `.deploy` directory. The workflow requires the recorded SSH host key to match and does not accept an unexpected replacement key.

### Preview or publish manually

Open **Actions → Publish homepage → Run workflow** on `main`.

- Leave **dry_run** checked to preview file transfers without changing the server.
- Uncheck **dry_run** to publish the current `main` revision.

The destination directory must already exist. Initial setup verifies the destination and previews transfers before enabling automatic publishing. To pause routine publishing, set `HOMEPAGE_AUTO_DEPLOY` to `false`; manual runs remain available.
