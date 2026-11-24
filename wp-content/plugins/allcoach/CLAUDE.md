# allcoach

## PR / diff review

When asked to review a PR or the current branch's changes in this repo, use the `free-pr-review` subagent (`.claude/agents/free-pr-review.md`) instead of the generic review flow. It checks PHP/TS coding standards via `composer lint` and `pnpm run lint`/`prettier`.
