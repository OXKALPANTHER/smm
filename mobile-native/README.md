# Royal SMM Native Companion

`mobile-native` is an **Expo / React Native** replacement for the existing Capacitor web wrapper. It provides native navigation, safe-area aware screens, role-aware customer and administrator workspaces, an inbox-first notification surface, and a live-panel connection screen.

The implementation intentionally contains no fabricated orders, balances, notifications, ratings, or other operational records. Until the live API is enabled, the app displays honest empty and connection states.

## Run locally

Install dependencies with `pnpm install`, then run `pnpm start`. Use Expo Go or an Android/iOS simulator to open the app.

## Required live API contract

The production PHP panel is currently page and session based. A native application needs an authenticated JSON layer; the native client is already prepared for these HTTPS routes:

| Route | Purpose |
|---|---|
| `GET /api/mobile/bootstrap` | Returns the signed-in user, their orders, their notifications, and the unread count. |
| `GET /api/mobile/notifications` | Returns the role-appropriate inbox. |
| `POST /api/mobile/notifications/:id/read` | Marks an accessible notification as read. |
| `POST /api/mobile/admin/notifications` | Sends a customer, broadcast, or admin notification after role validation. |

All routes should authenticate with a short-lived bearer token issued after a secure native sign-in flow. They must enforce the existing user/admin visibility rules on the server; the role switch in the app is only a presentation decision until live authentication is connected.

## Visual system

The app follows the **Operator’s Signal Room** direction with Royal Signal Violet `#6C5CE7`, asymmetric operational hierarchy, and compact signal rails instead of generic notification cards.

