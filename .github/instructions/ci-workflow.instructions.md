---
applyTo: ".github/workflows/*.yml,script/ci/**/*.php,phpunit.xml.dist,composer.json"
---

When reviewing CI and quality-gate related changes in this repository:

- Validate against the project's wrapper scripts first (`script/ci/*`) before proposing direct vendor binary calls.
- Treat workflow reliability issues as high priority:
  - failing jobs caused by missing optional coverage drivers
  - unstable cache path assumptions
  - command mismatches between workflow and `composer` scripts
- For PHPUnit coverage suggestions, ensure coverage source filters are configured and that no-coverage fallback behavior is preserved.
- Keep recommendations compatible with this project's current workflow file and script names; avoid renaming jobs/steps unless necessary.
- Distinguish between mandatory failures (non-zero exit by design) and warnings that should not break the pipeline.
