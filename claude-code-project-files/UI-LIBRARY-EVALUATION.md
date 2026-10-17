# UI/Animation Library Evaluation — RJM Boardinghouse System

## Decision
**Tailwind CSS** (visual design system) + **GSAP** (animation/motion layer). Both framework-agnostic and compatible with the PHP-rendered, vanilla-JS, localhost architecture in `ARCHITECTURE.md` without changing it. *As built (Phase 8 and later), neither is loaded from a CDN: GSAP and ScrollTrigger are vendored in `public/assets/js/vendor/`, and Tailwind is compiled once with its CLI into `public/assets/css/app.css`, so the running app makes no outbound request. Node is needed only to rebuild the CSS (see Implementation notes).*

## Why these two, evaluated against 8 candidates
Evaluated: Framer Motion/Motion, Three.js, GSAP, Tailwind CSS, Chakra UI, Material-UI, Ant Design, react-spline. Scored for fit with *this* project's actual stack (vanilla HTML/CSS/JS + PHP, no build tooling assumed), not general popularity.

| Library | Verdict | Reason |
|---|---|---|
| **Tailwind CSS** | ✅ Chosen | Utility CSS, no framework lock-in, drops into PHP-rendered HTML directly. v4 (2025) is a rewritten, faster engine with a zero-build CDN path. |
| **GSAP** | ✅ Chosen | Framework-agnostic, works directly on the DOM. As of April 30, 2025, 100% free including all previously-paid plugins (SplitText, MorphSVG, ScrollTrigger) — the historical reason teams avoided it is gone. |
| Motion (formerly Framer Motion) | Backup option | Now has a vanilla-JS build (~12KB gzipped) since its 2025 rebrand, not just React. Lighter than GSAP for simple fades/hovers — use if GSAP feels heavy for a one-line transition. |
| Three.js | Rejected for this project | Full WebGL 3D engine — real capability, wrong tool for an admin CRUD app; would cost load performance for no functional gain. Revisit only if a 3D bed-map view becomes a stretch goal. |
| Chakra UI | Rejected for this project | React component library — requires adopting React + a build pipeline, not a drop-in style/animation layer. |
| Material-UI | Rejected for this project | Same reason as Chakra UI. |
| Ant Design | Rejected for now, worth revisiting later | React-only, same stack-adoption cost — but its design philosophy (data tables, stat cards, admin dashboards) is unusually well matched to the Command Center specifically. Worth a second look **only if** the project later commits to a React rebuild of that one screen. |
| react-spline | Rejected for this project | React wrapper around a 3D design tool — same stack mismatch as Three.js, plus a dependency on Spline's export format. |

## Implementation notes
- **Update (Phase 8 polish):** both libraries are now vendored locally under `public/assets/{css,js}` instead of loaded from a CDN — the CDN path was a genuine violation of CLAUDE.md's "no outbound internet calls from the running app," since it required internet access on every page load. Vendoring was a one-time step, not an added runtime build pipeline; the localhost-only constraint is intact either way.
- The token/component source lives at `public/assets/css/source/input.css` (`@theme` block + `@layer components`). To regenerate `public/assets/css/app.css` after editing it or the views, run from the **project root**, on a machine with Node available:
  ```
  npm install --no-save tailwindcss@4 @tailwindcss/cli@4
  npx @tailwindcss/cli -i public/assets/css/source/input.css -o public/assets/css/app.css --minify
  ```
  Run `npm install` from the project root specifically — Node resolves `@import "tailwindcss"` by walking up from the input file, so `node_modules` needs to land at the root. Delete `node_modules` afterward; it's a one-time local tool, not something the app ships or runs with. The `@source` directives in `input.css` point at `src/Views/**/*.php` and `public/**/*.php` so the compiler only generates classes actually used in the templates — verified end-to-end.
- To update the vendored GSAP build: `npm install --no-save gsap@3`, then copy `node_modules/gsap/dist/gsap.min.js` and `ScrollTrigger.min.js` into `public/assets/js/vendor/`.
- GSAP `ScrollTrigger` for dashboard reveal-on-scroll; plain `.to()`/`.from()` tweens for the SOS pulse and maintenance priority badges.
- Respect `prefers-reduced-motion` via GSAP's `matchMedia()` helper — required by the accessibility floor in `ARCHITECTURE.md` §8.
- Spend animation budget on one or two signature moments (SOS alert, request-resolved confirmation), not everything — per the `frontend-design` skill's restraint principle.

## Shared UI conventions (15 Oct 2026)
Use these instead of styling a page from scratch; they live in `public/assets/css/source/input.css` unless noted.
- **Icons:** `<?= \App\Support\Icon::svg('bell') ?>` (`src/Support/Icon.php`), inline stroke SVGs that take the text's size and colour. No emoji as icons; `tests/test_ui_icons.php` checks both rules. Scripts that build markup use the same paths (see `icon()` in `ai-assistant.js`).
- **Confirmation dialogs:** `.modal` (backdrop, starts `style="display:none"`, page JS sets `display:flex`) › `.modal-card` › `.modal-icon` (`-danger`/`-success`/`-warning`), `.modal-title`, `.modal-text`, `.modal-actions` with `.btn` buttons. The entrance is CSS and replays on each open; focus the Cancel button on open.
- **Buttons:** `.btn-primary` (ink) for the main action, `.btn-secondary` beside it, `.btn-danger-solid` only for actions that remove or charge. While a form submits, `app.js` adds `.is-loading` (spinner, no second click) unless the page cancelled the submit.
- **Empty states:** `.empty-state` with `<span class="empty-icon">icon</span>`; on a `<td>` it stays a table cell so `colspan` works.
- **Motion:** `--duration-fast` 150ms (hover/press), `--duration-base` 200ms (toggles), `--duration-slow` 300ms (dialogs); curves `--ease-out-soft` and `--ease-spring`. Animate `transform`/`opacity` only; `prefers-reduced-motion` is handled once at the bottom of `input.css`.
- **Breakpoints with the sidebar:** at `lg` (1024px) the sidebar takes 240px, leaving ~770px. Grids of form cards or wide cards go to 3–4 columns at `xl`, not `lg`.
