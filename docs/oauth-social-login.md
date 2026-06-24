# 3rd-party OAuth (Social Login)

- **Google and Apple sign-in** for shop customers and admin users — sign-in buttons are rendered on the shop login + register pages and on the admin login page
- **Independent shop/admin configuration** — each provider is enabled and configured separately for the shop and admin groups, so you can register two distinct OAuth clients (different client IDs, consent screens, redirect URIs). Useful when the shop-facing app and the internal admin app live as separate applications on the provider side
- **Three callback flows** depending on what the plugin finds for the OAuth identity's email:
  - existing linked account → straight log-in
  - email matches a local account → a **single-use, time-limited 6-digit code** is emailed to that address; entering it creates the link (prevents account takeover, and works even for accounts that have no password — e.g. ones created through another social provider). Only the code's SHA-256 hash is kept in the session; the bundle's shared `CodeChallengeValidator` enforces the expiry, a capped number of guesses (after which the code is burned) and a constant-time comparison, while the plugin controller handles delivery (generating + emailing the code, CSRF, re-send limits) and maps the outcome to a message
  - email is unknown → a new account is auto-registered and the social identity linked (admin auto-registration is gated by an email-domain whitelist; see below)
- **Multiple providers per user** — links live in dedicated entities (`three_brs_customer_social_account_link`, `three_brs_admin_user_social_account_link`)
- **Link / unlink from the account page** — `LastAuthMethodGuard` refuses to unlink the last remaining sign-in method (password or another social link), so a user can never lock themselves out
- **Setting a password for OAuth-only accounts** — a customer auto-registered through OAuth has no password, so they can only sign back in through their connected provider. The account menu and the account dashboard therefore show a **"Set a new password"** entry in place of "Change password", which lets them create an initial password without being prompted for a current one (there is none). Once a password is set, the entry reverts to the standard "Change password" flow and the customer can also sign in with their email and password.
- **Extensible provider registry** — add Facebook, GitHub, LinkedIn, … without forking the plugin. Implement `OAuthProviderInterface` (`getName`, `isEnabledForCustomer`, `isEnabledForAdmin`, `getAuthorizationUrl`, `fetchUserInfo`) and tag the service with `three_brs.oauth_provider`. `OAuthProviderRegistry` collects every tagged provider and the login controllers / Twig templates pick them up automatically — no routing, controller or template changes needed. `fetchUserInfo()` returns an `OAuthUserInfoInterface` (email, first/last name, provider user ID, email-verified flag) used uniformly across the link / register / login flow
- **Apple specifics handled** — JWT ES256 `client_secret` generated at runtime from `team_id` / `key_id` / private key, `form_post` callback, first-auth-only name persisted, private relay emails accepted as-is
- **Apple works out of the box (no session tweak needed)** — Apple returns its callback as a cross-site `POST` (`response_mode=form_post`), so the browser does not send the default `SameSite=Lax` session cookie and the OAuth `state` would otherwise be lost. The plugin carries the `state` (and, for account linking, the initiating user) in a dedicated `SameSite=None; Secure; HttpOnly`, HMAC-signed, single-use cookie, verifies its signature on the callback — so the carried identity cannot be forged on a crafted (e.g. curl) request — and clears it immediately. You do **not** need to relax your application's `framework.session.cookie_samesite`. Because the cookie is `Secure`, Apple sign-in requires HTTPS (Apple mandates HTTPS redirect URIs anyway). Google and Microsoft are unaffected — they use a normal GET redirect and keep using the session.
- **Microsoft specifics handled** — Microsoft Identity Platform v2.0 endpoint, multi-tenant via `common` (personal + work/school) by default, single-tenant restriction available per group via `tenant: '<guid>'`, `mail` claim preferred with `userPrincipalName` fallback
- **Fixture** (`three_brs_social_account_link`) to preload social links for demo/testing

```yaml
three_brs_sylius_enterprise_security:
    oauth:
        customer:
            auto_register_allowed_email_domains: []    # empty = no restriction (any verified email); add e.g. ['yourcompany.com'] to restrict
            google:
                enabled: false
                client_id: '%env(GOOGLE_CLIENT_ID)%'
                client_secret: '%env(GOOGLE_CLIENT_SECRET)%'
            apple:
                enabled: false
                client_id: '%env(APPLE_CLIENT_ID)%'
                team_id: '%env(APPLE_TEAM_ID)%'
                key_id: '%env(APPLE_KEY_ID)%'
                private_key_path: '%kernel.project_dir%/config/secrets/apple_private_key.p8'
            microsoft:
                enabled: false
                client_id: '%env(MICROSOFT_CLIENT_ID)%'
                client_secret: '%env(MICROSOFT_CLIENT_SECRET)%'
                tenant: 'common'                       # 'common' = personal + work/school; use a tenant GUID for single-tenant restriction
        admin:
            default_locale: 'en_US'                    # locale assigned to auto-registered admins
            auto_register_allowed_email_domains: []    # empty = auto-registration disabled; add e.g. ['yourcompany.com']
            google:
                enabled: false
                client_id: '%env(GOOGLE_ADMIN_CLIENT_ID)%'
                client_secret: '%env(GOOGLE_ADMIN_CLIENT_SECRET)%'
            apple:
                enabled: false
                client_id: '%env(APPLE_ADMIN_CLIENT_ID)%'
                team_id: '%env(APPLE_TEAM_ID)%'
                key_id: '%env(APPLE_ADMIN_KEY_ID)%'
                private_key_path: '%kernel.project_dir%/config/secrets/apple_admin_private_key.p8'
            microsoft:
                enabled: false
                client_id: '%env(MICROSOFT_ADMIN_CLIENT_ID)%'
                client_secret: '%env(MICROSOFT_ADMIN_CLIENT_SECRET)%'
                tenant: 'common'                       # for admin/B2B consider 'organizations' (work/school only) or a tenant GUID (single org)
```

> **Limits (enforced in the Security Settings UI):** `auto_register_allowed_email_domains` — at most 100 entries, each at most 253 characters.

Callback URLs to register with the providers:

- Shop: `https://<your-domain>/oauth/{provider}/callback`
- Admin: `https://<your-domain>/admin/oauth/{provider}/callback`

> **Admin auto-registration:** by default `auto_register_allowed_email_domains` is empty and admin auto-registration is **disabled** — an unknown OAuth identity hitting the admin login is rejected. Add your corporate domain(s) to opt in. Auto-created admins receive `ROLE_ADMINISTRATION_ACCESS` and the configured `default_locale`.
>
> **Warning:** the `allowed_email_domains` whitelist should include **only domains you fully control**.
> Anyone with a working email in these domains can auto-create an admin account with full `ROLE_ADMINISTRATION_ACCESS`.
> For external/shared domains or when fine-grained control is needed, leave the whitelist empty — admins will need to be created manually before their first OAuth login.

> **Customer auto-registration:** by default `auto_register_allowed_email_domains` is empty and any verified OAuth identity can auto-register as a customer (preserves the commercial signup-friendly default). Populate the list to restrict customer auto-registration to specific domains (useful e.g. for B2B stores or as a bot-mitigation measure).

## Google Cloud setup

1. Open the [Google Cloud Console](https://console.cloud.google.com/) and create (or select) a project.
2. **APIs & Services → OAuth consent screen** — choose *External*, fill in the app name, support email and developer contact. Add the scopes `openid`, `email`, `profile`. Add test users while the app is in *Testing* mode.
3. **APIs & Services → Credentials → Create credentials → OAuth client ID**:
   - Application type: *Web application*
   - Authorized JavaScript origins: `https://<your-domain>`
   - Authorized redirect URIs: `https://<your-domain>/oauth/google/callback` (shop) and/or `https://<your-domain>/admin/oauth/google/callback` (admin)
4. Copy the generated **Client ID** and **Client secret** into your `.env.local`:
   ```dotenv
   GOOGLE_CLIENT_ID=...
   GOOGLE_CLIENT_SECRET=...
   GOOGLE_ADMIN_CLIENT_ID=...
   GOOGLE_ADMIN_CLIENT_SECRET=...
   ```
   Shop and admin can share a single OAuth client, but separate clients are recommended so you can revoke/rotate them independently.
5. Flip `enabled: true` for the relevant group in `threebrs_sylius_enterprise_security_plugin.yaml`.

## Apple Developer setup

Apple Sign In requires a paid Apple Developer account and a **public HTTPS** redirect URL — `http://localhost` is not accepted. For local testing expose your dev host over HTTPS (ngrok, Cloudflare Tunnel, …).

1. In the [Apple Developer portal](https://developer.apple.com/account/resources/) → **Certificates, Identifiers & Profiles**:
   - **Identifiers → App IDs → +** — create an App ID, enable the *Sign In with Apple* capability.
   - **Identifiers → Services IDs → +** — create a Services ID (this becomes the `client_id`), enable *Sign In with Apple*, configure the primary App ID and add your return URL: `https://<your-domain>/oauth/apple/callback` (and/or the admin variant).
   - **Keys → +** — create a key with *Sign In with Apple* enabled, associate it with the primary App ID, download the `.p8` private key. **The file is only downloadable once.** Note the **Key ID**.
2. Find your **Team ID** in the top-right of the Apple Developer portal (or under *Membership*).
3. Store the private key inside the project (outside of version control) and set env vars:
   ```dotenv
   APPLE_CLIENT_ID=com.yourcompany.sylius.signin       # the Services ID
   APPLE_TEAM_ID=ABCDE12345
   APPLE_KEY_ID=FGHIJ67890
   # path is configured in yaml: %kernel.project_dir%/config/secrets/apple_private_key.p8
   ```
4. Flip `enabled: true` for the relevant group. The plugin generates Apple's ES256 `client_secret` JWT at runtime — you don't store a long-lived secret.

## Microsoft Entra ID setup

Microsoft uses the Identity Platform v2.0 endpoint. The plugin defaults to the multi-tenant `common` authority — any Microsoft account (personal `outlook.com`/`hotmail.com`/`live.com` or work/school Azure AD) can sign in. For admin or B2B use cases set `tenant:` to your organization's tenant GUID (or `organizations` for any work/school account) to lock sign-ins to that audience.

1. In the [Microsoft Entra admin center](https://entra.microsoft.com/) → **Identity → Applications → App registrations → New registration**:
   - **Name**: e.g. *Sylius Sign In*
   - **Supported account types**: pick *Accounts in any organizational directory and personal Microsoft accounts* for `tenant: common`, *Accounts in any organizational directory* for `tenant: organizations`, or *Accounts in this organizational directory only* for a single-tenant restriction.
   - **Redirect URI**: choose *Web* and enter `https://<your-domain>/oauth/microsoft/callback` (shop) and/or `https://<your-domain>/admin/oauth/microsoft/callback` (admin). You can register both URIs on a single app or use two separate app registrations to rotate them independently.
2. **Certificates & secrets → Client secrets → New client secret** — give it a description and an expiry, then copy the *Value* immediately (it is shown only once).
3. **API permissions → Add a permission → Microsoft Graph → Delegated permissions** — make sure `openid`, `profile`, `email` and `User.Read` are granted (they are the default delegated set, so usually nothing extra to do).
4. Copy the values into your `.env.local`:
   ```dotenv
   MICROSOFT_CLIENT_ID=...                 # Application (client) ID from the Overview blade
   MICROSOFT_CLIENT_SECRET=...             # Client secret Value (not the Secret ID)
   MICROSOFT_ADMIN_CLIENT_ID=...
   MICROSOFT_ADMIN_CLIENT_SECRET=...
   ```
   Shop and admin can share a single app registration, but separate registrations are recommended so secrets and audience restrictions can be rotated independently.
5. Set `tenant:` in `threebrs_sylius_enterprise_security_plugin.yaml` to match the *Supported account types* you picked in step 1, then flip `enabled: true` for the relevant group.
