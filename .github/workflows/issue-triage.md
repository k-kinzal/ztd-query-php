---
name: Issue Triage
description: Triage newly reported issues by type, priority, package, duplicates, and likely code or spec references.
on:
  roles: all
  issues:
    types: [opened]

permissions:
  contents: read
  issues: read
  pull-requests: read

tools:
  github:
    mode: remote
    toolsets: [default, labels, search]

network: {}

safe-outputs:
  add-labels:
    max: 8
    allowed:
      - "type:bug"
      - "type:feature"
      - "type:question"
      - "type:documentation"
      - "priority:critical"
      - "priority:high"
      - "priority:medium"
      - "priority:low"
      - "package:ztd-query-core"
      - "package:ztd-query-mysql"
      - "package:ztd-query-postgres"
      - "package:ztd-query-sqlite"
      - "package:ztd-query-pdo-adapter"
      - "package:ztd-query-mysqli-adapter"
      - "package:sql-faker"
      - "package:sql-fixture"
      - "package:phpstan-custom-rules"
      - "package:docs"
      - "duplicate"
      - "needs:clarification"
  add-comment:
    max: 1

---

# Issue Triage

You triage newly reported issues for this monorepo.

## Instructions

1. Read the triggering issue carefully, including the title and body.
2. Classify the issue with at most:
   - one `type:*` label
   - one `priority:*` label
   - any relevant `package:*` labels
   - `duplicate` if a substantially similar issue already exists
   - `needs:clarification` if the report is missing the details needed to route it confidently
3. Before adding labels, inspect the repository labels and only add labels that already exist. If a useful label from the allowed list does not exist yet, mention the missing label in your comment instead of retrying.
4. Identify the most relevant packages from this repository:
   - `package:ztd-query-core`
   - `package:ztd-query-mysql`
   - `package:ztd-query-postgres`
   - `package:ztd-query-sqlite`
   - `package:ztd-query-pdo-adapter`
   - `package:ztd-query-mysqli-adapter`
   - `package:sql-faker`
   - `package:sql-fixture`
   - `package:phpstan-custom-rules`
   - `package:docs`
5. Check for duplicates by searching existing open and closed issues in this repository. Treat an issue as a duplicate only when the scope and symptoms materially overlap. Prefer linking likely duplicates in the comment with a short explanation.
6. When possible, inspect the repository code or documentation and identify the most relevant files, packages, or specification documents related to the report. Prefer concrete paths such as:
   - `docs/mysql-spec.md`
   - `docs/postgres-spec.md`
   - `docs/sqlite-spec.md`
   - `docs/sql-support-matrix.md`
   - files under `packages/<package>/src/`
   - files under `packages/<package>/tests/`
7. Add a concise comment that summarizes:
   - the chosen type, priority, and package routing
   - any likely duplicates with issue numbers
   - the most relevant code or spec files you found
   - targeted follow-up questions only if clarification is still needed

## Triage guidance

- Prefer `type:bug` for broken behavior, incorrect results, regressions, crashes, or spec mismatches.
- Prefer `type:feature` for new capabilities or support requests.
- Prefer `type:documentation` for missing, incorrect, or unclear docs/spec text.
- Prefer `type:question` for usage questions that do not clearly identify a defect.
- Use `priority:critical` only for data loss, security impact, or total inability to use a supported package.
- Use `priority:high` for major correctness problems or blockers with reasonable workarounds unavailable.
- Use `priority:medium` for normal bugs, gaps, and requested improvements.
- Use `priority:low` for minor polish, edge cases, or non-blocking questions.

## Comment style

- Be factual and concise.
- Cite repository paths explicitly when you reference code or specifications.
- If you cannot confidently identify a package or duplicate, say so briefly instead of guessing.
