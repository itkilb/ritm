# Disabled framework auth/token routes (task B)

These Apiato/Passport route files are intentionally **not loaded**: Apiato only
scans `UI/API/Routes`, and the copies here use the `.php.disabled` suffix.

Disabled because they competed with the product auth contract and could bypass
its validation/rate limits:

| Disabled route | Former file |
| --- | --- |
| `POST /v1/register` | `RegisterUser.v1.private.php` |
| `POST /v1/logout` (Passport token revocation) | `RevokeToken.v1.private.php` |
| `POST /v1/clients/web/login` | `WebClient/IssueToken.v1.private.php` |
| `POST /v1/clients/web/refresh` | `WebClient/RefreshToken.v1.private.php` |
| `/v1/oauth/*` (Passport) | `Passport.v1.private.php` |

Product auth is served by `POST /v1/auth/register`, `POST /v1/auth/login`,
`POST /v1/auth/logout` and `GET /v1/me`, backed by a Sanctum cookie session.
The Passport package and its migrations remain installed because Apiato and
the existing framework containers depend on them; only the public token routes
are disabled. There is no client-facing auth token.
