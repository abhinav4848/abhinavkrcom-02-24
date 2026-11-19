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

GitHub publishing and website deployment are separate steps. The initial repository update does not configure or trigger a deployment to the hosting account.

The next step is a GitHub Actions workflow that connects to the Linux hosting account over SSH and transfers only `index.htm`, `css/`, and `image/` into the site's `02_24` directory. Deployment will not copy `.git`, root routing files, WordPress, or other applications.

The hosting connection must be configured and verified before enabling automatic deployment. Store deployment credentials in GitHub Actions Secrets, outside the source files.
