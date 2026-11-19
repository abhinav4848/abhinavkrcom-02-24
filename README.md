# abhinavkr.com homepage

Source files for the homepage at <https://abhinavkr.com/>.

## Design preferences

The homepage follows the Creator direction with readable Manrope sans-serif type, comfortable line spacing, and a persistent light/dark theme toggle. Avoid the crowded, decorative serif typography used in the earlier Editorial and Personal desk concepts.

Keep all four existing gallery photos. The journey video uses site/image/abhinav3.jpg; the junior doctor salary video uses site/image/studio.jpg. Preserve the social accounts, travel channel (@DrAbhinavLifestyle), writing and project links, original Kit signup, and expandable visiting card with its full-size QR image.

Keep newsletter signup in a closed-by-default dropdown so it does not take up a full section of the homepage.

## Folder structure

```text
site/
  index.html
  css/
    style.css
  image/
scripts/
  deploy-homepage.sh
.github/
  workflows/
    deploy-homepage.yml
README.md
```

`site/` contains the public website. `scripts/` contains publishing commands. `.github/workflows/` tells GitHub when to run those commands. Repository and local setup files are kept outside `site/`.

The server serves a static `public_html/index.html` directly. The homepage no longer needs a PHP wrapper or the dated `02_24` folder.

## Working copy

```sh
git clone https://github.com/abhinav4848/abhinavkrcom-02-24.git abhinavkr-homepage
cd abhinavkr-homepage
```

Use the clean checkout for future edits. The original FTP-transferred Git metadata is not part of this repository's website files. Existing GitHub history is preserved.

## Preview locally

```sh
python -m http.server --directory site 8000
```

Open <http://localhost:8000/>. The homepage is plain HTML, CSS, and browser JavaScript, so local preview needs no PHP or build step.

## Save changes to GitHub

```sh
git status
git diff
git add site
git commit -m "Update homepage"
git push origin main
```

When changing the workflow, publishing script, or documentation, stage those named files too. Review changes before committing. Git manages history in the local `.git` directory; that directory is never published as website content.

## Automatic publishing

The **Publish homepage** GitHub Actions workflow runs when pushes to `main` change `site/`, the publishing script, or the workflow. It uses a temporary GitHub computer to connect to the hosting account over SSH and upload the homepage.

| Repository source | Hosting destination |
| --- | --- |
| `site/index.html` | `public_html/index.html` |
| `site/css/` | `public_html/css/` |
| `site/image/` | `public_html/image/` |

The contents of `site/` are published without adding a `/site/` prefix to the website address. Relative CSS, image, gallery, and visiting-card paths work at the domain root and under the hosting account's temporary URL prefix.

The script explicitly transfers only the three paths above, sending assets before HTML. It never copies repository metadata, credentials, scripts, or documentation. It does not delete older assets or neighbouring applications. WordPress and other folders in `public_html` remain outside this repository's deployment scope.

The host-provided temporary address is:

<http://sh00003.bigrock.tempwebhost.net/~sa1792ca/>

Publishing does not change DNS. The current domain remains on its existing hosting until a separate migration is arranged. WordPress and other applications need their own migration before switching the whole domain.

## Repository settings

Under **Settings → Secrets and variables → Actions**:

| Kind | Name | Purpose |
| --- | --- | --- |
| Variable | `HOMEPAGE_SSH_HOST` | Verified hosting IP or hostname |
| Variable | `HOMEPAGE_SSH_PORT` | Hosting SSH port |
| Variable | `HOMEPAGE_SSH_USER` | Hosting account username |
| Variable | `HOMEPAGE_DIRECTORY` | Absolute document root, ending in `/public_html` |
| Variable | `HOMEPAGE_AUTO_DEPLOY` | `true` enables publishing on pushes; `false` pauses it |
| Secret | `HOMEPAGE_SSH_KEY` | Dedicated deployment private key |
| Secret | `HOMEPAGE_KNOWN_HOSTS` | Pinned SSH host key for this server |

The public deployment key is authorized in cPanel. Its private half is stored in GitHub Actions Secrets and the ignored local `.deploy` folder. Keep an encrypted backup of the private key, public key, and `known_hosts` file outside this computer. Neither the key nor local setup tools belong in `site/` or Git history. The workflow checks the pinned server identity before connecting.

## Preview or publish manually

Open **Actions → Publish homepage → Run workflow** on `main`.

- Leave **dry_run** checked to list proposed transfers without changing the server.
- Uncheck **dry_run** to publish the current revision.

The destination must already exist. To pause automatic publishing, set `HOMEPAGE_AUTO_DEPLOY` to `false`; manual runs remain available.

The former root PHP wrapper and `02_24` website are archived in a private `homepage-backups` directory under the hosting account's home folder, outside `public_html`. Routine publishing does not move or delete these backups.
