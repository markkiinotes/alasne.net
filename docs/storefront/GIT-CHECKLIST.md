# Git Checklist — Storefront Phase 4 Closeout / Phase 5 Kickoff

Phase 4 browser acceptance passed on 2026-09-11.

Phase 4 closeout commits:

```text
6f396d2 Complete Phase 4 returns and refund workflow
57ad103 Complete Phase 4 customer return communications and tracking
```

## 1. Confirm Phase 5 kickoff baseline

```powershell
cd C:\xampp\htdocs\alasne.net
git branch --show-current
git status --short
```

Expected:

```text
feature/returns-and-restocking
```

and no working-tree output before the documentation update.

## 2. Replace Phase 5A documentation

Replace:

```text
docs/storefront/ROADMAP.md
docs/storefront/CHANGELOG.md
docs/storefront/TESTING-CHECKLIST.md
docs/storefront/GIT-CHECKLIST.md
```

The update should:

- mark Phase 4 complete
- record Phase 4 acceptance coverage
- record the two Phase 4 closeout commits
- mark Phase 5 active
- establish the Phase 5A–5F audit plan

## 3. Review documentation diff

```powershell
git diff --check
git diff --stat
git status --short
```

`git diff --check` should report no whitespace errors.

## 4. Stage only Phase 5A documentation

```powershell
git add docs/storefront/ROADMAP.md
git add docs/storefront/CHANGELOG.md
git add docs/storefront/TESTING-CHECKLIST.md
git add docs/storefront/GIT-CHECKLIST.md
```

Verify:

```powershell
git status --short
git diff --cached --stat
```

## 5. Commit Phase 5A baseline

Recommended commit:

```powershell
git commit -m "Close Phase 4 docs and begin storefront launch audit"
```

## 6. Push

```powershell
git push origin feature/returns-and-restocking
```

## 7. Verify clean baseline

```powershell
git status --short
git log -3 --oneline
```

Expected:

```text
working tree clean
latest commit = Phase 5A documentation baseline
previous commits = Phase 4 closeout commits
```

## 8. Begin Phase 5B

Next milestone:

```text
Phase 5B — Complete storefront browser journey
```

Run the customer-visible flow from catalog through checkout, account, order
tracking, returns, and protected print views before moving into responsive and
accessibility regression.
