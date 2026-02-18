---
applyTo: "app/**/*.php,lib/**/*.php,config/**/*.php,db/**/*.php,script/**/*.php,tests/**/*.php"
---

When reviewing PHP changes in this repository:

- Assume the project is a legacy/custom framework; preserve behavior unless explicitly changing it.
- Prefer conservative changes that keep route contracts and response formats stable.
- Treat runtime safety regressions as high priority (TypeErrors, fatal paths, null/type mismatches).
- Flag risky `array_merge`/param-shape assumptions and non-string input paths in validation/encoding code.
- For model/controller changes, check for missing authorization checks and unintended privilege expansion.
- Keep recommendations grounded in the existing stack and code style.
