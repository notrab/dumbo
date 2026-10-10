---
title: Set up with an agent
description: A copy and paste prompt that has your coding agent install Dumbo, write a starter app and check it runs.
---

# Set up with an agent

Coding agents are quick at scaffolding, and they're also quick at inventing framework methods that don't exist. This prompt covers both: it sets up a Dumbo app step by step, then hands your agent a cheat sheet of Dumbo's real API for everything you build afterwards.

::: prompt file="agent-setup.md" title="Set up a Dumbo app"
:::

## What the agent does

::: steps
1. Checks for PHP 8.3 or newer and Composer, and stops to tell you if either is missing.
2. Runs `composer init` if the folder is empty, then `composer require notrab/dumbo`.
3. Writes an `index.php` with a hello route and a `/health` route. If you already have an `index.php`, it asks first.
4. Adds a `composer start` script, starts the server, and checks both routes with `curl`.
5. Shows you what it made and asks what you'd like to build next.
:::

## Pointing agents at the URL

Agents that can fetch URLs can read the prompt from [/agent-setup.md](/agent-setup.md), so you can tell yours to "follow the instructions at" that address on this site.

::: callout type="tip" title="Keep the cheat sheet around"
The reference half of the prompt is useful long after setup. Drop it into your project's `CLAUDE.md`, `AGENTS.md` or rules file, and your agent will reach for real Dumbo APIs every time it adds a route.
:::
