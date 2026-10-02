---
name: agent-browser
description: Drive a browser from the CLI (open pages, snapshot, click, fill, screenshot) to verify the HTML/PHP UI of this project. Use when asked to check a page in a real browser or capture a screenshot.
---

# agent-browser

CLI browser automation (`npm install -g agent-browser`, installed version 0.27.0).

## Cloud-session setup

`agent-browser install` fails in the sandbox (downloads from googlechromelabs.github.io are blocked).
Use the pre-installed Chromium instead:

```bash
export AGENT_BROWSER_EXECUTABLE_PATH=/opt/pw-browsers/chromium
export AGENT_BROWSER_ARGS="--no-sandbox"
```

Do not run `agent-browser install` or `playwright install`.

## Basic workflow

```bash
agent-browser open http://localhost:8000/index.php   # navigate
agent-browser snapshot                               # accessibility tree with @refs
agent-browser fill @e3 "text"                        # fill by ref or selector
agent-browser click @e5
agent-browser screenshot /tmp/page.png
agent-browser close
```

Re-run `snapshot` after each page change; refs are regenerated.

## Useful commands

- `get text|html|value|title|url <sel>` — read page info
- `is visible|enabled|checked <sel>` — check state
- `find role|text|label <value> click` — locate by semantics
- `wait <sel|ms>`, `eval <js>`, `pdf <path>`
- `--session <name>` — isolated session (e.g. one per user role to test login/approval flows)

## Project notes

- This project is plain HTML/PHP served by Apache on a NAS (`http://192.168.111.49:8000`); that address is not reachable from the cloud sandbox. For local checks run `php -S localhost:8000` in the project directory and open that URL.
- Full command reference: `agent-browser skills get core --full`.
