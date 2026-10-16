# Project verification

- The local application uses PostgreSQL; PHPUnit uses SQLite `:memory:` as configured in `phpunit.xml`. Date aggregation must support both. `DashboardService::datePartExpression()` handles database-specific extraction without loading individual surveys.
- Dashboard verification: `php artisan test --filter="DashboardTest|SurveyManagementTest|SurveyFolderTest|CapRatingTest"`.
- Compile Blade with `php artisan view:cache`; build frontend assets with `npm run build`. Run Laravel Pint on the changed PHP files.
- Keep a literal HTML root outside conditional includes in Livewire views. In `pages.dashboard`, starting with `@if` + `@include` placed morph markers before the partial root; Livewire attached `wire:id` to the inner welcome banner, causing duplicated charts and a blank year selector after updates. Dashboard regression tests verify the root owns all analytics on initial render and repeated year changes.
- Dashboard survey analytics require both the `viewStats` Gate and the Survey `viewAny` Policy. Counts use survey dates, include all folders, and exclude soft-deleted surveys. CAP distribution and average use only completed surveys with a non-null score; zero is a valid score. Reuse `CapRating` for grade boundaries.
