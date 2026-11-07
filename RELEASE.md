# Release guide

Repository: `ITMOSOFT/laravel-subscriptions`

Description: `Plan and subscription lifecycle management for Laravel applications.`

Release title: `v1.0.0 — Initial Release`

Before publishing, run `composer install` and `composer test`, push to GitHub, and confirm both Laravel 12 and 13 GitHub Actions jobs pass. Then run:

```bash
git tag -a v1.0.0 -m "Initial release"
git push origin v1.0.0
```

Create the GitHub Release and submit `itmosoft/laravel-subscriptions` to Packagist with its GitHub webhook. Do not publish before CI passes.
