# Changelog

## [1.0.2] - 2026-11-25
- Validate persisted subscriber and plan models before starting subscriptions.
- Validate plan amounts, currencies, intervals and interval counts.
- Reject past or current trial end timestamps.
- Set next billing date consistently for trial and non-trial subscriptions.
- Revalidate active plan state under a database row lock.
- Ensure resume uses the locked database subscription period end.
- Reject lifecycle operations on unsaved subscriptions.
- Expand subscription lifecycle regression tests.

## [1.0.1] - 2026-11-07
- Retain the persisted subscriber model in the PHPUnit fixture so its primary key is available to subscription creation tests.

## [1.0.0] - 2026-11-07
- Initial release: plans, subscriber subscriptions, lifecycle operations and status history.
