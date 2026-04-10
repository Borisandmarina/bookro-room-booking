# AGENTS.md

This repository contains a WordPress plugin under active manual testing on a live hosting environment.

## Core editing rules
- Do NOT rewrite entire files.
- Do NOT change formatting.
- Do NOT rename functions, variables, hooks, constants, database fields, option names, or file names unless explicitly requested.
- Do NOT modify unrelated code.
- Do NOT refactor or optimize code unless explicitly requested.
- Do NOT add new features.
- Do NOT reorganize file structure.
- Apply only minimal, targeted changes required for the task.

## Change scope
- Prefer exact line edits over broad rewrites.
- If a task would require changing more than 10 lines in one file, stop and provide analysis first.
- If a requested fix requires touching additional unrelated code, stop, explain why, and ask for confirmation before changing it.

## WordPress-specific rules
- Preserve existing plugin behavior unless the task explicitly requires behavior changes.
- For security fixes, prefer minimal changes:
  - sanitize input as early as possible
  - escape output late
  - use nonce checks correctly
  - use capability checks where appropriate
  - use `$wpdb->prepare()` for SQL with variables
- For JS/CSS loading issues, prefer `wp_enqueue_script`, `wp_enqueue_style`, `wp_add_inline_script`, and `wp_add_inline_style` only when explicitly requested for that task.
- Do not introduce framework, build, or tooling changes.

## Output requirements
Before making changes:
1. Identify exact file(s) and exact location(s) to change.
2. Briefly state what will be changed.

After making changes:
- Show a minimal diff.
- Summarize only the exact changes made.
- Do not include unrelated suggestions.

## Workflow preference
- The user manually tests the plugin in WordPress after each change.
- Prefer one small safe change per task.
- Stability and minimal risk are more important than code style improvements.