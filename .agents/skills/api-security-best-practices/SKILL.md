---
name: api-security-best-practices
description: Guide agents to design, implement, review, and generate secure APIs by avoiding common real-world mistakes from production systems. Triggers on API security, backend validation, access control, business logic, external service trust, SSRF, sensitive data exposure, input validation, rate limiting, API inventory, or configuration hardening. Use whenever generating or reviewing API code, auth flows, or application security designs.
---

# API Security Best Practices

Apply these rules whenever designing, implementing, reviewing, or generating API-related code, authentication, authorization, or application flows. Never rely on client-side controls alone.

These rules come from the most frequent real-world failures that lead to data breaches, privilege escalation, and business logic abuse.

## 1. Never Trust the Frontend

**Core rule:** The frontend is completely untrusted. It exists only for user experience.

### Why this fails
Developers often hide admin buttons, edit forms, or premium features in the UI and assume "if the user can't see it, they can't use it." Attackers ignore the UI entirely and send requests directly to the backend.

### Concrete rules
- Treat every incoming request as if it came from a hostile script (curl, Postman, Burp, custom code).
- Never use frontend visibility, disabled buttons, or client-side route guards as a security control.
- Every endpoint that performs a privileged action must re-check the user's identity and permissions on the server.
- Feature flags, role checks, and "isAdmin" logic that only live in the frontend are worthless for security.

### What good looks like
- Backend middleware or handlers always answer: "Is this authenticated user allowed to perform this exact action on this exact resource right now?"
- Even if the frontend never shows an "Admin" button, the `/api/admin/*` routes still reject non-admin callers with 403.

### Anti-patterns to reject
- Code that only checks roles in React/Vue/Angular and assumes the API is safe.
- Comments like "this is hidden from regular users so it's fine."
- Returning different UI based on role but serving the same unrestricted API.

## 2. Enforce Proper Access Control (Broken Access Control)

This is one of the most common and highest-impact vulnerabilities.

### Always answer three questions on the server for every request
1. **Who** is making the request? (authenticated identity)
2. **What** action are they trying to perform? (permission / role check)
3. **Which** resource are they targeting? (object-level / ownership check)

### Common failure modes (block all of these)
- Horizontal privilege escalation: User A can read/update/delete User B's data by changing an ID in the URL or body.
- Vertical privilege escalation: A normal user can call admin-only endpoints.
- Mass assignment / over-posting: Client sends extra fields (role, isAdmin, price, balance) and the server blindly saves them.
- Missing function-level access control: An endpoint exists and works for anyone who knows the URL.

### Concrete rules
- Never trust an ID coming from the client without verifying the current user owns it or has explicit permission.
- Prefer server-side lookups that start from the authenticated user's identity rather than accepting arbitrary resource IDs.
- Use explicit permission checks (or a policy/authorization library) instead of scattered `if (user.role === "admin")` checks.
- For every mutation, verify both the action and the target resource.
- Reject requests that try to change fields the caller is not allowed to modify (whitelist allowed fields).

### What good looks like
```text
# Pseudo
user = require_authenticated_user()
resource = load_resource(id)
deny_unless(user.can("delete", resource))   # checks role + ownership
perform_delete(resource)
```

### Anti-patterns to reject
- `DELETE /api/users/{id}` with no ownership or role check.
- Updating a user object by merging the entire request body.
- Relying only on "the user is logged in" as sufficient authorization.

## 3. Prevent Business Logic Abuse

Individual endpoints can be perfectly authenticated and authorized, yet the overall process can still be abused.

### Why this fails
Applications have multi-step flows (cart → payment → rewards, signup → verification → access, order → ship → refund). Attackers skip or reorder steps.

### Classic example
E-commerce flow:
1. Add items to cart
2. Pay
3. Claim cashback / rewards

If the "claim rewards" endpoint only checks that the user is logged in, an attacker can call it without ever paying.

### Concrete rules
- Model the legitimate state machine of every important flow.
- Every step must re-validate that the previous required steps actually completed successfully.
- Store authoritative state on the server (never trust client-side "I already paid" flags).
- Prefer transactional or event-driven designs where later steps can only be reached after earlier steps emit verified events.
- Treat "happy path only" testing as insufficient — always test skipped and out-of-order steps.

### What good looks like
- "Claim reward" endpoint checks: order exists, payment status = paid, reward not already claimed, and the order belongs to the current user.
- State transitions are explicit and enforced (e.g., only move from `PENDING_PAYMENT` to `PAID`).

### Anti-patterns to reject
- Endpoints that only check authentication and then perform the final action of a multi-step process.
- Client-controlled flags such as `paymentCompleted: true` that the server trusts.
- Missing checks for "has this already been done?" (replay / double-claim).

## 4. Do Not Blindly Trust External APIs and Services

Modern applications depend on payment providers, identity providers, data enrichment services, etc.

### Why this fails
Developers treat the response from a trusted third party as gospel. If that service is compromised, misconfigured, or the attacker can inject a fake response, the whole application is compromised.

### Concrete rules
- Always validate tokens and responses yourself:
  - Signature
  - Issuer
  - Audience
  - Expiration
  - Expected claims / scopes
- Never accept a bearer token or identity assertion without cryptographic verification.
- Keep your own authorization decisions independent of the external service. Even if the IdP says the user is an admin, re-check against your own policy if needed.
- Assume the external service can return malicious or unexpected data.
- Prefer short-lived tokens and proper key rotation.

### What good looks like
- JWT validation with proper library, known JWKS, and strict claim checks.
- Payment webhook signatures verified before updating order status.
- Your application still enforces its own business rules after accepting an external identity.

### Anti-patterns to reject
- Decoding a JWT without verifying the signature.
- Trusting an external "user is verified" flag without your own checks.
- Using the external service as the sole source of truth for authorization.

## 5. Defend Against SSRF (Server-Side Request Forgery)

SSRF happens when the server makes an outbound request using data controlled by the attacker.

### Why this fails
Features like "fetch this URL", "import from this link", "webhook to this address", or "preview this image" often take a user-supplied URL and pass it to an HTTP client.

### Concrete rules
- Never pass user-controlled strings directly into HTTP clients, `fetch`, `curl`, image downloaders, or PDF generators.
- If a destination must be user-influenced, enforce a strict allow-list of permitted hosts and schemes.
- Block:
  - Private IP ranges (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16)
  - Localhost / loopback
  - Link-local and metadata endpoints (169.254.169.254 and cloud-specific equivalents)
  - Non-HTTP schemes (file://, gopher://, dict://, etc.)
- Resolve the hostname yourself, then validate the resulting IP address before connecting.
- Prefer fixed destinations or server-side configuration over user-supplied URLs whenever possible.

### What good looks like
- Allow-list of known image CDNs or partner domains.
- DNS resolution + IP classification before any connection is opened.
- Network-level egress controls as defense in depth.

### Anti-patterns to reject
- `http.get(userProvidedUrl)`
- "We only allow http/https so it's safe."
- Features that let users supply arbitrary webhook or callback URLs without validation.

## 6. Protect Sensitive Data

### Concrete rules
- Return only the fields the caller is authorized to see (principle of least privilege for data).
- Never include secrets, internal IDs, tokens, password hashes, or debug information in responses.
- Be careful with verbose error messages that reveal stack traces, SQL, or internal paths.
- Avoid logging sensitive values (tokens, PII, full request bodies on authenticated endpoints).
- Consider field-level encryption or tokenization for highly sensitive data.

### Anti-patterns to reject
- Returning the entire database row/object by default.
- Error responses that leak implementation details.
- Logging authorization headers or request bodies containing secrets.

## 7. Validate All Inputs

### Concrete rules
- Validate type, format, length, range, and allowed values on the server for every parameter (path, query, header, body).
- Prefer allow-lists over block-lists.
- Reject unexpected fields (do not silently ignore them if they could be used for mass assignment).
- Use schema validation (JSON Schema, OpenAPI validation, protobuf, etc.) as a first line of defense.
- Contextual output encoding to prevent injection (SQL, NoSQL, command, XSS, template, etc.).

### Anti-patterns to reject
- Trusting that the frontend already validated the data.
- Accepting arbitrary JSON and mapping it straight to a database model.
- Using string concatenation for queries or commands.

## 8. Apply Rate Limiting and Abuse Controls

### Concrete rules
- Rate-limit authentication, password reset, OTP, and any expensive or sensitive endpoints.
- Apply limits per user, per IP, and per API key as appropriate.
- Detect and throttle enumeration (user IDs, emails, order numbers), bulk actions, and repeated failures.
- Return generic responses for authentication failures so attackers cannot distinguish "user exists" vs "wrong password" when that distinction is sensitive.
- Consider progressive delays or CAPTCHA after repeated failures.

### Anti-patterns to reject
- Unlimited login or password-reset attempts.
- Endpoints that allow bulk deletion or export with no throttling.
- Detailed error messages that help attackers enumerate valid accounts.

## 9. Maintain Accurate API Inventory

### Concrete rules
- Keep a living inventory of every endpoint (public, internal, partner, admin, debug).
- Remove or properly protect shadow APIs, forgotten admin routes, old versions, and debug endpoints.
- Document the required authentication and authorization for every endpoint.
- Ensure security testing, monitoring, and logging cover the full inventory.
- Prefer generated inventories from code or OpenAPI over manually maintained lists that drift.

### Anti-patterns to reject
- "We only have the documented endpoints" while old `/v1/admin` or `/debug` routes still exist.
- Internal tools or health-check endpoints left open without authentication.

## 10. Secure Configuration and Deployment

### Concrete rules
- Disable debug mode, verbose errors, and stack traces in production.
- Never hard-code secrets; load them from a secrets manager or environment with proper access control.
- Restrict CORS to specific trusted origins (avoid `*` for credentialed requests).
- Limit allowed HTTP methods to those actually needed.
- Keep frameworks, libraries, and runtimes patched.
- Use least-privilege credentials for databases, message queues, object storage, and external services.
- Prefer short-lived credentials and automatic rotation where possible.

### Anti-patterns to reject
- Production deployments with `DEBUG=true` or detailed error pages.
- Secrets committed to source control or baked into container images.
- Overly permissive CORS or IAM roles.

## Universal Checklist (use on every endpoint)

Before generating, approving, or shipping any API endpoint, confirm:

1. Backend re-validates identity **and** permissions (who / what / which).
2. Business-state prerequisites and allowed transitions are enforced server-side.
3. No user-controlled input is used to build outbound requests without strict allow-listing and IP validation.
4. Response contains only data the caller is authorized to see.
5. All inputs are strictly validated (schema + business rules).
6. Appropriate rate limits and abuse detection exist.
7. The endpoint is recorded in the API inventory with correct auth requirements.
8. Production configuration is hardened (no debug, minimal surface area, secrets managed properly).

## Guidance for Code Generation and Reviews

When writing or reviewing code:
- Prefer explicit authorization checks over implicit assumptions.
- Prefer allow-lists over block-lists.
- Prefer server-authoritative state over client-supplied flags.
- Prefer short-lived, scoped tokens over long-lived powerful credentials.
- Default to deny. Only allow after positive proof of permission.
- Treat every new endpoint as a potential attack surface until proven otherwise.

These rules are not optional hardening — they are the minimum required to avoid the most common, high-impact API security failures seen in real systems.
