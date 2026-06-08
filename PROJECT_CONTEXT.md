# PROJECT CONTEXT

# PROJECT OVERVIEW

## Project Name

VLD Global Compensation System

## What The System Does

VLD Global Compensation System is a plain PHP and MySQL MLM web application that manages member registration, package activation, referral genealogy, product code encoding, bonuses, wallet balance, payouts, admin monitoring, and compensation reports.

The application runs locally in XAMPP under `/vld_global` and uses modular PHP includes instead of a framework. The member portal and admin portal are separate but share the same database connection through `config.php`.

The system is built around these core workflows:

- Members register with a sponsor username and a valid unused package activation code.
- Members log in with username and password.
- Members receive a unique generated member code.
- Sponsors earn direct referral bonuses when new members register.
- Higher uplines earn generation bonuses from package activations.
- Members encode product codes, which triggers community bonuses to uplines.
- All member wallet earnings and deductions are stored in `bonus_ledger`.
- Members can request payouts from their available ledger balance.
- Admin users manage members, packages, package codes, products, product codes, payouts, bonuses, reports, leadership ranking views, global pool views, and settings views.

## MLM Compensation Structure

The application currently supports these compensation concepts:

- Direct referral bonus from immediate sponsored member registration.
- Generation bonus for levels 2 through 5 above the new member's sponsor.
- Community bonus from product code encoding, paid up to 8 sponsor-upline levels.
- Leadership ranking display based on direct and rank hierarchy requirements.
- Global pool display calculated from total package sales.
- Payout deduction using a 10% fee plus a flat ₱100 fee.

The current implemented wallet source of truth is `bonus_ledger`. Positive rows represent earnings. Negative rows represent deductions such as payout requests.

## User Roles

## Member

Members use the front-facing portal:

- Register using `register.php`.
- Log in using `login.php`.
- View dashboard statistics.
- Copy referral links.
- View direct referrals.
- View genealogy.
- View generation and community bonus history.
- Encode product codes.
- View leadership ranking.
- View global pool qualification.
- Request payouts.
- View payout history.
- Update profile information.

Member sessions use:

- `$_SESSION['member_id']`
- `$_SESSION['username']`
- `$_SESSION['member_code']`

## Admin

Admins use the back-office portal under `/admin`:

- Log in using `admin/login.php`.
- View dashboard totals.
- View all members.
- Update package names, prices, and bonus values.
- Generate package activation codes.
- Add and update products.
- Generate product codes.
- View product purchases.
- View bonus ledger records.
- View payouts.
- View reports.
- View leadership rank calculations.
- View global pool calculations.
- View static settings.

Admin sessions use:

- `$_SESSION['admin_id']`
- `$_SESSION['admin_username']`

Current admin login is hardcoded in `admin/login.php`:

- Username: `admin`
- Password: `admin123`

## Authentication Flow

## Member Authentication

Member login is handled by `login.php`.

Flow:

1. Includes `functions.php`, which includes `config.php`.
2. `config.php` starts the PHP session and creates the MySQLi connection.
3. Login form submits `username` and `password`.
4. Username is lowercased and trimmed.
5. The system searches `members` for `username=?` and `status='active'`.
6. Password is verified using `password_verify()`.
7. On success, member session values are set.
8. JavaScript redirects to `/vld_global/index.php`.
9. Protected member routes include `includes/auth_check.php`, which redirects unauthenticated users to `/vld_global/login.php`.

Member logout is handled by `logout.php`, which includes `config.php`, calls `session_destroy()`, then redirects to `login.php`.

## Admin Authentication

Admin login is handled by `admin/login.php`.

Flow:

1. Includes `../config.php`.
2. Login form submits admin username and password.
3. Credentials are compared against hardcoded values.
4. On success, `admin_id` and `admin_username` are stored in session.
5. JavaScript redirects to `/vld_global/admin/index.php`.
6. Protected admin routes include `admin/includes/auth_check.php`, which redirects unauthenticated admins to `admin/login.php`.

Admin logout is handled by `admin/logout.php`, which unsets admin session keys and redirects to `admin/login.php`.

# TECHNICAL STACK

## Core Stack

- Plain PHP
- MySQL / MariaDB
- MySQLi prepared statements
- Bootstrap 5 CDN
- Plain CSS
- Vanilla JavaScript
- XAMPP local environment
- Modular PHP includes
- No Laravel
- No Composer
- No npm
- No frontend build tool
- No framework

## PHP Architecture

The project uses procedural PHP with shared include files. There are no controllers, models, migrations, packages, namespaces, autoloaders, or framework routing layers.

Core shared files:

- `config.php` starts the session and creates `$conn`.
- `functions.php` includes `config.php` and defines shared business helper functions.
- `includes/*.php` builds the member layout.
- `admin/includes/*.php` builds the admin layout.
- `pages/*.php` contains member page bodies.
- `admin/pages/*.php` contains admin page bodies.

The global database connection variable is:

```php
$conn
```

## Routing System

Routing is query-string based and implemented with `switch` statements.

Member router:

- Entry file: `index.php`
- Query parameter: `page`
- Default page: `dashboard`
- Example route: `index.php?page=dashboard`

Admin router:

- Entry file: `admin/index.php`
- Query parameter: `page`
- Default page: `dashboard`
- Example route: `admin/index.php?page=members`

Routes do not map directly from user input to file paths. The `switch` statement maps known page keys to known PHP files, then includes the selected file through the layout wrapper.

## Layout System

Member layout:

- `index.php` chooses `$page`.
- `main.php` includes authentication and layout sections.
- `includes/header.php` opens HTML and loads Bootstrap/member CSS.
- `includes/sidebar.php` starts the app shell and sidebar.
- `includes/topbar.php` renders the top navigation bar.
- `main.php` includes the selected `$page` inside `.content-wrapper`.
- `includes/footer.php` closes layout tags and loads Bootstrap JavaScript plus sidebar toggle JavaScript.

Admin layout:

- `admin/index.php` chooses `$page`.
- `admin/main.php` includes database connection, authentication, and layout sections.
- `admin/includes/header.php` opens HTML and loads Bootstrap/admin CSS.
- `admin/includes/sidebar.php` starts the admin shell and sidebar.
- `admin/includes/topbar.php` renders the admin top navigation bar.
- `admin/main.php` includes the selected `$page` inside `.admin-content`.
- `admin/includes/footer.php` closes layout tags and loads Bootstrap JavaScript plus sidebar toggle JavaScript.

## Session Handling

`config.php` calls `session_start()` and must be included before session variables are used.

Important rule:

- Avoid output before `config.php` or before any redirecting auth file because `header()` redirects require headers to be unsent.

Member protected routes depend on:

```php
$_SESSION['member_id']
```

Admin protected routes depend on:

```php
$_SESSION['admin_id']
```

## MySQLi Usage

The project uses MySQLi through:

```php
$conn = new mysqli($host, $user, $pass, $db);
```

Current local connection values in `config.php`:

- Host: `localhost`
- User: `root`
- Password: blank
- Database: `vld_global`

User-supplied values should be handled with prepared statements:

```php
$stmt = $conn->prepare("SELECT * FROM members WHERE username=?");
$stmt->bind_param("s", $username);
$stmt->execute();
```

Some admin dashboard/report queries currently use direct `$conn->query()` because they contain no user input. Future user-input queries must use prepared statements.

## Bootstrap Usage

Bootstrap 5 is loaded from CDN.

Member layout uses:

- `https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css`
- `https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js`

Login and registration pages use Bootstrap 5 CDN directly and include page-local CSS.

Admin layout also uses Bootstrap 5.3.3 CDN and its own CSS file under `admin/assets/style.css`.

# DIRECTORY STRUCTURE

## Root Files

## `config.php`

Starts the session and creates the global MySQLi connection.

Defines:

- `$host`
- `$user`
- `$pass`
- `$db`
- `$conn`

Any PHP script that needs session or database access must include this directly or indirectly.

## `functions.php`

Includes `config.php` and defines shared member/bonus helpers:

- `generateMemberCode($conn)`
- `getMemberByUsername($conn, $username)`
- `getUpline($conn, $member_id, $levels = 8)`
- `addBonus($conn, $member_id, $amount, $type, $desc)`
- `processCommunityBonus($conn, $member_id, $quantity)`
- `getBalance($conn, $member_id)`
- `getCashbackStatus($conn, $member_id)`
- `getDominanceRoyaltySummary($conn, $member_id)`
- `processCashbackAndAdvancement($conn, $member_id)`
- `useDominanceAdvancementCreditForUpgrade($conn, $member_id)`

This file is the main reusable business logic location.

## `index.php`

Member portal router. Reads `$_GET['page']`, maps it to a file under `pages/`, then includes `main.php`.

Old `index.php?page=members` links redirect to `index.php?page=directs`.

Known member page keys:

- `dashboard`
- `genealogy`
- `directs`
- `generation_bonus`
- `community_bonus`
- `encode_product`
- `leadership_ranking`
- `global_pool`
- `dominance_upgrade`
- `payout`
- `payout_history`
- `profile`

## `main.php`

Member layout wrapper. Includes:

- `includes/auth_check.php`
- `includes/header.php`
- `includes/sidebar.php`
- `includes/topbar.php`
- selected `$page`
- `includes/footer.php`

## `login.php`

Member login page. Authenticates active members by username and password hash.

## `register.php`

Member registration and package activation page. Handles:

- Optional referral link sponsor prefill through `?ref=username`
- Username validation
- Email validation
- Password confirmation
- Sponsor validation
- Package activation code validation
- Member creation
- Package code usage update
- Direct referral bonus
- Generation bonus

## `logout.php`

Destroys member session and redirects to `login.php`.

## `reset_root_password.php`

Utility script that resets the member account with username `root` to password `123456` and activates the account.

Important:

- This is a development/helper script.
- It should not remain publicly accessible in production.

## `database/db.sql`

SQL dump containing the current core table structures.

Current physical tables in the dump:

- `bonus_ledger`
- `cashback_ledger`
- `community_bonus_ledger`
- `dominance_advancement_credits`
- `dominance_royalty_ledger`
- `members`
- `packages`
- `package_codes`
- `payouts`
- `products`
- `product_codes`
- `product_purchases`

## `assets/`

Member-facing assets:

- `assets/style.css`
- `assets/logo.png`
- `assets/logo2.png`
- `assets/back/logo.png`
- `assets/back/logo - Copy.png`

## `assets/style.css`

Main member portal stylesheet. Defines the VLD green, red, purple, and dark theme; premium sidebar; topbar; dashboard cards; responsive behavior; and mobile hamburger sidebar behavior.

# MEMBER DIRECTORY STRUCTURE

## `includes/`

Member layout includes.

## `includes/auth_check.php`

Protects member pages. Redirects to `/vld_global/login.php` if `$_SESSION['member_id']` is missing or empty.

## `includes/header.php`

Opens the HTML document, sets viewport, loads Bootstrap CDN, and loads `assets/style.css`.

## `includes/sidebar.php`

Member sidebar navigation. Starts the `.app-shell` and `.premium-sidebar` layout.

Current member navigation:

- Does not show the deprecated Members menu item.
- Uses Direct Referrals as the official direct-member page.
- Shows Dominance Upgrade only to Vision and Legacy members.
- Hides Dominance Upgrade completely for Dominance members.

## `includes/topbar.php`

Member topbar with hamburger button and logout button.

## `includes/footer.php`

Closes layout tags, loads Bootstrap JS CDN, and contains JavaScript for mobile sidebar open/close behavior.

## `pages/`

Member content pages included by `index.php`.

## `pages/dashboard.php`

Shows:

- Wallet balance from `getBalance()`
- Direct member count
- Product encode count
- Member code
- Package name
- Referral link
- Profile summary
- Account information
- Cashback status
- Dominance Credit card for Vision/Legacy members
- Dominance Royalty card for Dominance members
- Active Direct Dominance count for Dominance members

Referral link format:

```text
http://{host}/vld_global/register.php?ref={username}
```

## `pages/members.php`

Deprecated as a member route. The member router redirects old `index.php?page=members` links to `index.php?page=directs`.

## `pages/directs.php`

Official Direct Referrals page.

Shows direct referrals where `members.sponsor_id` equals the current logged-in member ID.

Features:

- Search by member code, username, full name, contact number, package, or status.
- Pagination.
- Bootstrap 5 responsive table.
- Member code.
- Username.
- Full name.
- Contact number.
- Package.
- Status.
- Date joined.

## `pages/genealogy.php`

Recursively displays downline members sponsored by the current member.

Current implementation uses a recursive PHP function inside the page:

```php
buildTree($conn, $member_id, $level = 0)
```

## `pages/generation_bonus.php`

Shows `bonus_ledger` rows for the current member where:

```sql
type = 'generation_bonus'
```

## `pages/community_bonus.php`

Shows `community_bonus_ledger` rows for the current member and joins the source member username.

## `pages/encode_product.php`

Allows members to encode unused product codes.

Flow:

1. Member submits a product code.
2. System checks `product_codes` where `code=?` and `status='unused'`.
3. System inserts a row into `product_purchases`.
4. System marks the product code as `used`.
5. System calls `processCommunityBonus()`.
6. Community bonuses are inserted into `community_bonus_ledger`.
7. Matching wallet earnings are inserted into `bonus_ledger`.

## `pages/leadership_ranking.php`

Displays current computed rank and rank requirements.

Current implemented rank logic:

- `L1` if member has at least 10 direct referrals.
- `No Rank` otherwise.

Displayed future hierarchy:

- L1: 10 directs
- L2: 5 L1
- L3: 3 L2
- L4: 2 L3
- L5: 2 L4
- L6: 2 L5

## `pages/global_pool.php`

Displays global pool qualification for the current member.

Current logic:

- Counts direct referrals where `package_id IN (2,3)`.
- Member is qualified if they have at least 5 Legacy/Dominance directs.
- Calculates total package sales from all members joined to packages.
- Calculates global pool as 2% of total package sales.

This page currently displays calculations only. It does not insert global pool payouts into `bonus_ledger`.

## `pages/dominance_upgrade.php`

Allows qualified Vision or Legacy members to consume an unused Dominance Advancement Credit. This page is hidden from Dominance members and manually opened Dominance links redirect to the dashboard.

Flow:

1. Member must have an unused row in `dominance_advancement_credits`.
2. Credit is not added to `bonus_ledger`.
3. Upgrade runs through `useDominanceAdvancementCreditForUpgrade()`.
4. Member `package_id` is updated to Dominance.
5. Credit status is changed to `used`.
6. Matching `cashback_ledger` advancement record is changed to `used`.

## `pages/payout.php`

Allows a member to request payout from wallet balance.

Flow:

1. Balance is calculated from `SUM(amount)` in `bonus_ledger`.
2. Member submits requested amount.
3. System validates amount is positive and not greater than balance.
4. Fee is calculated as 10% plus ₱100.
5. Net amount is calculated as requested amount minus total fee.
6. Row is inserted into `payouts`.
7. Negative row is inserted into `bonus_ledger` with type `payout`.

## `pages/payout_history.php`

Displays payout records for the current member from `payouts`.

## `pages/profile.php`

Allows member to update `full_name` and `email`.

# ADMIN DIRECTORY STRUCTURE

## `admin/index.php`

Admin portal router. Reads `$_GET['page']`, maps it to a file under `admin/pages/`, then includes `admin/main.php`.

Known admin page keys:

- `dashboard`
- `members`
- `packages`
- `products`
- `product_purchases`
- `bonuses`
- `cashback`
- `royalty`
- `payouts`
- `leadership_ranks`
- `global_pool`
- `reports`
- `settings`
- `package_codes`
- `product_codes`

## `admin/main.php`

Admin layout wrapper. Includes:

- `../config.php`
- `admin/includes/auth_check.php`
- `admin/includes/header.php`
- `admin/includes/sidebar.php`
- `admin/includes/topbar.php`
- selected `$page`
- `admin/includes/footer.php`

## `admin/login.php`

Admin login page with hardcoded admin credentials.

## `admin/logout.php`

Clears admin session keys and redirects to `admin/login.php`.

## `admin/assets/style.css`

Admin portal stylesheet. Uses the same VLD-inspired green, red, purple, dark color direction with an AdminLTE-style sidebar, dashboard cards, responsive layout, and hamburger sidebar behavior.

## `admin/includes/`

Admin layout includes:

- `admin/includes/auth_check.php`
- `admin/includes/header.php`
- `admin/includes/sidebar.php`
- `admin/includes/topbar.php`
- `admin/includes/footer.php`

## `admin/pages/dashboard.php`

Shows admin totals:

- Total members
- Total package sales
- Total bonuses
- Total payouts
- Product purchases
- Unused package codes
- Unused product codes
- Recent members

## `admin/pages/members.php`

Displays all members with package and sponsor information.

## `admin/pages/packages.php`

Allows admin to update:

- Package name
- Package price
- Direct bonus
- Generation bonus

## `admin/pages/package_codes.php`

Generates package activation codes.

Code format:

```text
PKG-{random hex}-{timestamp}-{sequence}
```

Generated rows are inserted into `package_codes` with `status='unused'`.

## `admin/pages/products.php`

Allows admin to add and update products.

## `admin/pages/product_codes.php`

Generates product codes.

Code format:

```text
PRD-{random hex}-{timestamp}-{sequence}
```

Generated rows are inserted into `product_codes` with:

- selected product ID
- quantity per code
- status `unused`

## `admin/pages/product_purchases.php`

Displays encoded product purchase records by joining:

- `product_purchases`
- `members`
- `products`

## `admin/pages/bonuses.php`

Displays all `bonus_ledger` records with member username and full name.

## `admin/pages/payouts.php`

Displays all payout records with member username and full name.

## `admin/pages/reports.php`

Displays reports:

- Members by package
- Bonuses by type
- Purchases by product

## `admin/pages/leadership_ranks.php`

Displays computed leadership rank for all members.

Current implemented admin rank logic:

- `L1` if member has at least 10 directs.
- `No Rank` otherwise.

## `admin/pages/global_pool.php`

Displays calculated global pool information:

- Total package sales
- Pool percentage
- Total pool amount
- Qualified members
- Estimated share per qualified member

Qualification:

- At least 5 direct members with `package_id IN (2,3)`.

This page currently displays estimates only. It does not insert global pool payout entries into `bonus_ledger`.

## `admin/pages/settings.php`

Displays static settings values:

- System name
- Payout percentage fee
- Payout flat fee
- Community bonus rule

Current settings are not stored in a database table.

# DATABASE STRUCTURE

## Current Database

Database name:

```text
vld_global
```

Current live database and `database/db.sql` contain these physical tables:

- `bonus_ledger`
- `cashback_ledger`
- `community_bonus_ledger`
- `dominance_advancement_credits`
- `dominance_royalty_ledger`
- `members`
- `package_codes`
- `packages`
- `payouts`
- `product_codes`
- `product_purchases`
- `products`

These requested business tables/pages are currently implemented as calculated/static views but are not physical tables in the current schema:

- `leadership_ranks`
- `global_pool`
- `settings`

## Important Database Rule

The current schema does not define foreign key constraints. Relationships are logical and enforced by application code. Future database changes must preserve compatibility with existing table names and columns unless a full migration plan is created.

## `members`

Stores member accounts and sponsor relationships.

Columns:

- `id` int primary key auto increment
- `username` varchar(50), unique
- `member_code` varchar(20)
- `full_name` varchar(100)
- `email` varchar(100)
- `password` varchar(255)
- `sponsor_id` int
- `package_id` int
- `created_at` datetime default current timestamp
- `status` varchar(20) default `active`

Logical relationships:

- `members.sponsor_id` references `members.id`
- `members.package_id` references `packages.id`

Usage:

- Authentication
- Referral tree
- Direct referral counts
- Genealogy
- Package ownership
- Leadership rank calculations
- Global pool qualification

## `packages`

Stores package definitions and package-based bonus amounts.

Columns:

- `id` int primary key auto increment
- `name` varchar(50)
- `price` decimal(10,2)
- `direct_bonus` decimal(10,2)
- `generation_bonus` decimal(10,2)

Current package rows:

- Vision: ₱3,887 price, ₱500 direct bonus
- Legacy: ₱6,893 price, ₱1,000 direct bonus
- Dominance: ₱50,885 price, ₱10,000 direct bonus

Logical relationships:

- `members.package_id` references `packages.id`
- `package_codes.package_id` references `packages.id`

Usage:

- Registration package activation
- Direct referral bonus amount
- Generation bonus amount
- Total package sales calculation
- Global pool calculation

## `package_codes`

Stores package activation codes generated by admin.

Columns:

- `id` int primary key auto increment
- `code` varchar(100), unique
- `package_id` int
- `used_by_member_id` int
- `status` varchar(20) default `unused`
- `created_at` datetime default current timestamp
- `used_at` datetime nullable

Logical relationships:

- `package_codes.package_id` references `packages.id`
- `package_codes.used_by_member_id` references `members.id`

Usage:

- Required during member registration.
- A code can only be used when `status='unused'`.
- After successful registration, code is marked `used`, `used_by_member_id` is set, and `used_at` is set to `NOW()`.

## `products`

Stores product definitions.

Columns:

- `id` int primary key auto increment
- `name` varchar(100)

Current product row:

- Perfume

Logical relationships:

- `product_codes.product_id` references `products.id`
- `product_purchases.product_id` references `products.id`

Usage:

- Product code generation
- Product purchase history
- Product quantity reports

## `product_codes`

Stores product encode codes generated by admin.

Columns:

- `id` int primary key auto increment
- `code` varchar(100), unique
- `product_id` int
- `quantity` int
- `used_by_member_id` int
- `status` varchar(20) default `unused`
- `created_at` datetime default current timestamp
- `used_at` datetime nullable

Logical relationships:

- `product_codes.product_id` references `products.id`
- `product_codes.used_by_member_id` references `members.id`

Usage:

- Members encode these codes in `pages/encode_product.php`.
- Each code carries a product and quantity.
- Product quantity determines community bonus amount.
- After successful encoding, code is marked `used`, `used_by_member_id` is set, and `used_at` is set to `NOW()`.

## `product_purchases`

Stores product code encodes by members.

Columns:

- `id` int primary key auto increment
- `member_id` int
- `product_id` int
- `quantity` int
- `created_at` datetime default current timestamp

Logical relationships:

- `product_purchases.member_id` references `members.id`
- `product_purchases.product_id` references `products.id`

Usage:

- Product encode history.
- Dashboard product encode count.
- Admin product purchase reports.
- Trigger point for community bonus calculations.

## `bonus_ledger`

Stores all wallet-affecting earnings and deductions.

Columns:

- `id` int primary key auto increment
- `member_id` int
- `amount` decimal(10,2)
- `type` varchar(50)
- `description` text
- `created_at` datetime default current timestamp

Logical relationships:

- `bonus_ledger.member_id` references `members.id`

Current known `type` values:

- `direct_referral`
- `generation_bonus`
- `community`
- `payout`

Usage:

- Wallet balance source of truth.
- Admin bonus ledger.
- Member generation bonus display.
- Payout deductions.
- Future compensation features must insert wallet-affecting rows here.

Balance calculation:

```sql
SELECT SUM(amount) AS total
FROM bonus_ledger
WHERE member_id=?
```

Important rule:

- Positive amounts are earnings.
- Negative amounts are deductions.
- All earning features must pass through this table.

## `cashback_ledger`

Stores one-time cashback and Dominance Advancement qualification records.

Columns:

- `id` int primary key auto increment
- `member_id` int
- `package_id` int
- `qualification_type` varchar(80)
- `qualified_direct_count` int
- `reward_type` varchar(50)
- `reward_amount` decimal(10,2)
- `bonus_ledger_id` int nullable
- `status` varchar(20)
- `created_at` datetime default current timestamp

Allowed `reward_type` values:

- `cashback`
- `dominance_advancement_credit`

Allowed `status` values:

- `active`
- `used`
- `cancelled`

Important rules:

- Unique key on `member_id` and `reward_type` prevents duplicate reward rows.
- Withdrawable cashback rows link to `bonus_ledger`.
- Dominance Advancement Credit rows do not link to `bonus_ledger` because they are not withdrawable.

## `dominance_advancement_credits`

Stores non-withdrawable Dominance upgrade credits.

Columns:

- `id` int primary key auto increment
- `member_id` int
- `amount` decimal(10,2)
- `status` varchar(20)
- `used_at` datetime nullable
- `created_at` datetime default current timestamp

Allowed `status` values:

- `unused`
- `used`
- `cancelled`

Important rules:

- Unique key on `member_id` prevents duplicate credits.
- Credit is not withdrawable and must never be inserted into `bonus_ledger`.
- Credit can only be consumed to upgrade package to Dominance.

## `dominance_royalty_ledger`

Stores the six-month Dominance Royalty Bonus schedule.

Columns:

- `id` int primary key auto increment
- `member_id` int
- `month_no` int
- `amount` decimal(10,2)
- `bonus_type` varchar(50)
- `bonus_ledger_id` int nullable
- `available_at` datetime
- `status` varchar(20)
- `created_at` datetime default current timestamp

Important rules:

- Unique key on `member_id` and `month_no` prevents duplicate royalty schedules.
- Each schedule row links to a `bonus_ledger` row with type `dominance_royalty_bonus`.
- Royalty rows are created immediately after Dominance qualification.
- Released/available royalty is determined by `available_at <= NOW()`.
- Pending royalty is determined by `available_at > NOW()`.
- No cron job, admin release, or manual processing is required.

## `community_bonus_ledger`

Stores detailed community bonus records.

Columns:

- `id` int primary key auto increment
- `member_id` int
- `from_member_id` int
- `level` int
- `amount` decimal(10,2)
- `created_at` datetime default current timestamp

Logical relationships:

- `community_bonus_ledger.member_id` references the earning `members.id`
- `community_bonus_ledger.from_member_id` references the member who encoded the product code

Usage:

- Detailed audit trail for community bonuses.
- Member community bonus page.
- Each row should correspond to a wallet entry in `bonus_ledger` with type `community`.

## `payouts`

Stores payout requests.

Columns:

- `id` int primary key auto increment
- `member_id` int
- `amount` decimal(10,2)
- `fee` decimal(10,2)
- `net_amount` decimal(10,2)
- `created_at` datetime default current timestamp

Logical relationships:

- `payouts.member_id` references `members.id`

Usage:

- Member payout history.
- Admin payout list.
- Records requested gross amount, total fee, and net amount.
- A matching negative `bonus_ledger` row deducts the requested payout amount from wallet balance.

Current limitation:

- No payout status column exists yet.
- No approval/reject workflow exists yet.

## `leadership_ranks`

Requested business table, but not currently present in the physical database.

Current implementation:

- Member page `pages/leadership_ranking.php` computes rank directly from referral counts.
- Admin page `admin/pages/leadership_ranks.php` computes rank directly from referral counts.
- Only L1 is currently computed in code.
- L2 to L6 requirements are displayed but not fully computed.

If added in the future, this table should store rank definitions or member rank achievements without breaking existing computed views.

Recommended future role:

- Store rank code such as `L1`, `L2`, `L3`, `L4`, `L5`, `L6`.
- Store rank name or description.
- Store direct requirement or downline rank requirement.
- Store member rank achievements in a separate history table if rank tracking needs auditability.

## `global_pool`

Requested business table, but not currently present in the physical database.

Current implementation:

- Member page `pages/global_pool.php` calculates qualification and pool amount dynamically.
- Admin page `admin/pages/global_pool.php` calculates total pool and estimated shares dynamically.
- Pool percentage is hardcoded as 2%.
- Qualified members require at least 5 direct members with Legacy or Dominance packages.
- No global pool earnings are inserted into `bonus_ledger`.

If added in the future, this table should track pool periods, total sales, pool percentage, pool amount, qualified member count, and distribution status.

Recommended future role:

- Store pool calculation batches.
- Store payout periods.
- Store whether a pool batch has been distributed.
- Prevent redistributing the same pool period.
- Insert actual pool earnings into `bonus_ledger` only once per member per pool period.

## `settings`

Requested business table, but not currently present in the physical database.

Current implementation:

- `admin/pages/settings.php` displays hardcoded read-only settings.
- POST only displays `Settings saved.` and does not persist anything.

If added in the future, this table should store editable system configuration such as:

- System name
- Payout percentage fee
- Payout flat fee
- Community bonus per quantity
- Community bonus max levels
- Global pool percentage
- Rank requirements
- Maintenance flags

# TABLE RELATIONSHIPS

## Member Sponsorship

```text
members.sponsor_id -> members.id
```

This self-reference creates the MLM tree.

## Member Package

```text
members.package_id -> packages.id
```

Each active registered member has a selected package from their activation code.

## Package Activation Code

```text
package_codes.package_id -> packages.id
package_codes.used_by_member_id -> members.id
```

Package codes connect admin-generated activation inventory to registered members.

## Product Code

```text
product_codes.product_id -> products.id
product_codes.used_by_member_id -> members.id
```

Product codes connect product inventory and quantities to member encodes.

## Product Purchase

```text
product_purchases.member_id -> members.id
product_purchases.product_id -> products.id
```

Product purchases are created when members encode product codes.

## Bonus Ledger

```text
bonus_ledger.member_id -> members.id
```

Every wallet-affecting earning and deduction must be tied to a member.

## Community Bonus Ledger

```text
community_bonus_ledger.member_id -> members.id
community_bonus_ledger.from_member_id -> members.id
```

`member_id` is the earner. `from_member_id` is the member whose product encode generated the community bonus.

## Payout

```text
payouts.member_id -> members.id
```

Payout records belong to members and should have a matching deduction in `bonus_ledger`.

# MEMBER SYSTEM

## Username Login

Members log in using username and password.

Username behavior:

- Converted to lowercase during login.
- Converted to lowercase during registration.
- Must match `/^[a-z0-9]+$/` during registration.
- Must be unique in the `members` table.

Password behavior:

- Stored using `password_hash()`.
- Verified using `password_verify()`.
- Minimum length during registration is 6 characters.

Status behavior:

- Login only succeeds for members where `status='active'`.

## Sponsor System

Every new member must provide a valid sponsor username.

Registration rejects:

- Missing sponsor
- Nonexistent sponsor username
- Self-referral where username equals sponsor username
- Duplicate username
- Invalid package code
- Used package code

Sponsor relationship is stored as:

```text
members.sponsor_id = sponsor member ID
```

## Referral Links

Referral links are generated on the member dashboard.

Format:

```text
register.php?ref={username}
```

When a referral link is opened:

1. `register.php` reads `$_GET['ref']`.
2. The username is lowercased and trimmed.
3. `getMemberByUsername()` checks if the referrer exists.
4. If valid, sponsor username is prefilled.
5. Sponsor field becomes readonly.

## Package Activation

Registration requires an unused package code.

Validation query joins:

- `package_codes`
- `packages`

Required code condition:

```sql
pc.code=? AND pc.status='unused'
```

After registration succeeds:

- New member is inserted into `members`.
- Package code is marked `used`.
- `used_by_member_id` is set to the new member ID.
- `used_at` is set to `NOW()`.
- Direct referral bonus is inserted.
- Generation bonuses are inserted.
- Sponsor cashback or Dominance Advancement Credit qualification is checked through `processCashbackAndAdvancement($conn, $sponsor_id)`.

## Genealogy And Upline

The genealogy tree is based on repeated lookup of members where:

```text
members.sponsor_id = parent member ID
```

Upline lookup is handled by:

```php
getUpline($conn, $member_id, $levels = 8)
```

This function:

- Starts from a member ID.
- Reads that member's `sponsor_id`.
- Adds each sponsor to the upline array by level.
- Walks upward until there is no sponsor or the level limit is reached.

Community bonus uses up to 8 levels.

Generation bonus currently uses uplines above the sponsor for levels 2 to 5.

# BONUS SYSTEM

## Cashback And Dominance Advancement Program

The finalized package names are:

- Vision: package ID 1
- Legacy: package ID 2
- Dominance: package ID 3

Reward priority:

1. If a Vision or Legacy member has 5 active Direct Dominance referrals, issue Dominance Advancement Credit only.
2. Else if a Vision member has 5 active Vision/Legacy direct referrals in any mix, issue ₱3,887 withdrawable cashback.
3. Else if a Legacy member has 5 active Legacy/Dominance direct referrals in any mix, issue ₱6,893 withdrawable cashback.
4. Else if a Dominance member has 5 active Direct Dominance referrals, issue ₱50,885 withdrawable cashback.

Important rules:

- Cashback is one-time only.
- Cashback is withdrawable.
- Cashback is inserted into `bonus_ledger` with type `cashback_bonus`.
- Cashback detail is recorded in `cashback_ledger`.
- Dominance Advancement Credit is one-time only.
- Dominance Advancement Credit is not withdrawable.
- Dominance Advancement Credit is not inserted into `bonus_ledger`.
- Dominance Advancement Credit is recorded in `dominance_advancement_credits` and `cashback_ledger`.
- The credit can only be used by `useDominanceAdvancementCreditForUpgrade()` to upgrade a member to Dominance.
- A member must not receive both normal cashback and Dominance Advancement Credit.

Dominance Royalty rule:

- A Dominance member with 5 active Direct Dominance referrals receives immediate ₱50,885 cashback.
- The same qualification creates a Dominance Royalty schedule.
- Royalty amount is ₱5,000 per month for 6 months.
- Total royalty schedule is ₱30,000.
- `bonus_ledger.type` is `dominance_royalty_bonus`.
- Month 1 releases 30 days after qualification.
- Month 2 releases 60 days after qualification.
- Month 3 releases 90 days after qualification.
- Month 4 releases 120 days after qualification.
- Month 5 releases 150 days after qualification.
- Month 6 releases 180 days after qualification.
- The rows are inserted immediately into `dominance_royalty_ledger` and `bonus_ledger`.
- `getBalance()` only counts royalty rows whose `available_at <= NOW()`.
- Pending royalty remains outside withdrawable balance until its `available_at` date.

Reusable functions:

- `processCashbackAndAdvancement($conn, $member_id)`
- `getQualifiedCashbackDirects($conn, $member_id)`
- `hasCashbackAlreadyClaimed($conn, $member_id)`
- `hasDominanceAdvancementCredit($conn, $member_id)`
- `processDominanceAdvancementCredit($conn, $member_id)`
- `useDominanceAdvancementCreditForUpgrade($conn, $member_id)`
- `createDominanceRoyaltySchedule($conn, $member_id)`
- `getDominanceRoyaltySummary($conn, $member_id)`
- `hasDominanceRoyaltySchedule($conn, $member_id)`

## Direct Referral Bonus

Trigger:

- Successful member registration with an unused package activation code.

Recipient:

- Immediate sponsor.

Amount source:

```text
packages.direct_bonus
```

Ledger entry:

```text
type = direct_referral
description = Direct referral bonus from {username}
```

Current package direct bonuses:

- Vision: ₱500
- Legacy: ₱1,000
- Dominance: ₱10,000

## Generation Bonus

Trigger:

- Successful member registration with an unused package activation code.

Recipients:

- Uplines above the immediate sponsor.
- Current code starts from the sponsor's upline.
- Displayed generation levels are 2 through 5.

Amount source:

```text
packages.generation_bonus
```

Ledger entry:

```text
type = generation_bonus
description = Generation level {level} bonus from {username}
```

Current package generation bonuses:

- Vision: ₱100
- Legacy: ₱200
- Dominance: ₱1,000

Important current detail:

- `getUpline($conn, $sponsor_id, 4)` returns up to 4 uplines above the sponsor.
- The code labels them as generation levels 2, 3, 4, and 5.

## Community Bonus

Trigger:

- Member successfully encodes an unused product code.

Recipients:

- Up to 8 uplines of the encoding member.

Amount:

```text
quantity * ₱5
```

Per-level rule:

- Each qualified upline level receives ₱5 per encoded quantity.
- Maximum 8 levels.

Records inserted:

1. `community_bonus_ledger`
2. `bonus_ledger`

Community ledger values:

- `member_id` = earning upline member ID
- `from_member_id` = encoding member ID
- `level` = upline level
- `amount` = quantity times 5

Bonus ledger entry:

```text
type = community
description = Level {level} bonus from member {member_id}
```

## Leadership Ranking

Current implementation is computed from member downline data and does not use a dedicated database table.

Current active computed logic:

- L1 if member has at least 10 direct referrals.
- No Rank otherwise.

Displayed rank rules:

- L1: 10 Directs
- L2: 5 L1
- L3: 3 L2
- L4: 2 L3
- L5: 2 L4
- L6: 2 L5

Future implementation should decide whether ranks are:

- Computed live from tree data.
- Stored as member achievement records.
- Recalculated by an admin action.
- Calculated in a scheduled process.

## Global Pool

Current implementation is calculated live and does not use a dedicated database table.

Current rules:

- Total package sales = sum of package prices for registered members.
- Global pool = 2% of total package sales.
- A member qualifies with at least 5 direct referrals whose `package_id` is 2 or 3.
- Package ID 2 is Legacy.
- Package ID 3 is Dominance.

Admin page estimates:

- Total pool amount.
- Qualified member count.
- Equal share per qualified member.

Member page shows:

- Total package sales.
- Pool amount.
- Member's Legacy/Dominance direct count.
- Qualification status.

Important current limitation:

- Global pool shares are not inserted into `bonus_ledger`.
- There is no pool period table.
- There is no distribution history.
- There is no duplicate-distribution prevention yet.

## Payout Deductions

Trigger:

- Member submits payout request.

Balance source:

```text
SUM(bonus_ledger.amount)
```

Current validation:

- Requested amount must be positive.
- Requested amount must not exceed wallet balance.

Fee calculation:

```text
fee = amount * 10% + ₱100
net_amount = amount - fee
```

Records inserted:

1. `payouts` row with gross amount, fee, and net amount.
2. `bonus_ledger` row with negative requested amount.

Ledger entry:

```text
type = payout
amount = -{requested amount}
description = Payout request
```

# CURRENT BUSINESS RULES

## Packages

- Vision: ₱3,887
- Legacy: ₱6,893
- Dominance: ₱50,885

## Direct Referral Bonus

- Vision: ₱500
- Legacy: ₱1,000
- Dominance: ₱10,000

## Generation Bonus

- Levels 2 to 5
- Amount depends on package:
- Vision: ₱100
- Legacy: ₱200
- Dominance: ₱1,000

## Community Bonus

- 8 levels
- ₱5 per product quantity per level
- Triggered by product code encoding

## Global Pool

- 2% of total package sales
- Qualification requires 5 direct Legacy/Dominance members
- Current implementation is estimated/display-only

## Dominance Royalty Bonus

- Qualification requires Dominance package and 5 active Direct Dominance members.
- Immediate cashback is ₱50,885 through `cashback_bonus`.
- Royalty is ₱5,000 monthly for 6 months.
- Total royalty is ₱30,000.
- Royalty rows are created immediately.
- Released royalty uses `available_at <= NOW()`.
- Pending royalty uses `available_at > NOW()`.
- No cron job, admin release, or manual processing is used.

## Leadership Ranking

- L1: 10 directs
- L2: 5 L1
- L3: 3 L2
- L4: 2 L3
- L5: 2 L4
- L6: 2 L5
- Current code only computes L1

## Payout

- 10% fee
- ₱100 flat fee
- Deducts full requested amount from wallet through `bonus_ledger`
- Stores gross, fee, and net in `payouts`

# CODE STANDARDS

## Required Coding Style

- Plain PHP only.
- No Laravel.
- No Composer.
- No npm.
- No framework.
- No new build system.
- Use modular PHP includes.
- Keep current procedural style unless a future refactor is explicitly requested.
- Return full updated scripts when asked for code changes.
- Do not provide partial snippets when modifying existing scripts.
- Use reusable functions for repeated business logic.
- Keep existing route keys and include structure.
- Keep Bootstrap 5 CDN only.
- Use plain CSS files for styling.
- Keep the application mobile responsive.
- Match the existing visual style.

## Database Access Rules

- Use MySQLi prepared statements for all user input.
- Bind parameters using correct types.
- Keep using `$conn` unless a broader database abstraction is explicitly requested.
- Do not introduce PDO unless requested.
- Do not introduce ORM or migration tooling unless requested.
- Escape output with `htmlspecialchars()` when rendering user-controlled data.

## Bonus And Money Rules

- All wallet earnings must pass through `bonus_ledger`.
- All wallet deductions must pass through `bonus_ledger`.
- Use transactions for multi-step bonus computations.
- Prevent duplicate bonus entries.
- Preserve existing `type` values unless a migration plan is included.
- For new bonus types, use clear stable type names.
- Store enough description data to audit why the bonus was created.
- Never calculate available wallet balance from display pages; use ledger totals.

## Transaction Rules

Any process that changes more than one table should use a database transaction.

Critical transaction candidates:

- Member registration
- Package code usage
- Direct referral bonus
- Generation bonus
- Product code usage
- Product purchase insert
- Community bonus ledger insert
- Bonus ledger insert
- Payout request
- Future global pool distribution
- Future leadership bonus distribution

Recommended pattern:

```php
$conn->begin_transaction();

try {
    // prepared statements and writes
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    throw $e;
}
```

## Duplicate Prevention Rules

Future compensation code should prevent duplicates through one or more of:

- Unique database keys.
- Idempotency checks before insert.
- Source table references.
- Batch IDs.
- Period IDs.
- Code status locks.
- Transactions.

Examples:

- A package code must only activate one member once.
- A product code must only be encoded once.
- A direct referral bonus must only be paid once per registration.
- A generation bonus must only be paid once per registration/upline/level.
- A community bonus must only be paid once per product code/upline/level.
- A global pool distribution must only run once per pool period.

# UI THEME

## Overall Design Direction

The application uses a premium MLM/dashboard style inspired by the VLD logo color palette.

Main visual qualities:

- Premium glassmorphism style on login and registration screens.
- Dark sidebar dashboard style.
- Green, red, purple, and dark theme.
- Bold card-based dashboard metrics.
- AdminLTE-style left sidebar navigation.
- Sticky topbars.
- Mobile hamburger sidebars.
- Responsive Bootstrap grid layouts.

## Member Theme

Member CSS file:

```text
assets/style.css
```

Defined color variables include:

- `--vld-green`
- `--vld-green-dark`
- `--vld-green-light`
- `--vld-red`
- `--vld-red-dark`
- `--vld-purple`
- `--vld-purple-dark`
- `--vld-dark`
- `--vld-panel`
- `--vld-light`
- `--vld-white`
- `--vld-text`
- `--vld-muted`
- `--vld-border`

Member portal design:

- Fixed dark premium sidebar.
- VLD logo at sidebar top.
- Green/red/purple accent gradients.
- White content background.
- Premium dashboard hero.
- Stat cards.
- Responsive sidebar overlay on mobile.

## Admin Theme

Admin CSS file:

```text
admin/assets/style.css
```

Defined color variables include:

- `--admin-green`
- `--admin-green-dark`
- `--admin-green-light`
- `--admin-red`
- `--admin-red-dark`
- `--admin-purple`
- `--admin-purple-dark`
- `--admin-deep`
- `--admin-dark`
- `--admin-bg`
- `--admin-text`
- `--admin-muted`
- `--admin-border`

Admin portal design:

- AdminLTE-style fixed sidebar.
- Dark management-console look.
- Green/red/purple dashboard stat cards.
- White admin content cards.
- Responsive hamburger sidebar.
- Sticky topbar.

## Login And Registration Theme

Login and registration pages use page-local CSS with Bootstrap CDN.

Visual direction:

- Large premium glass cards.
- VLD logo.
- Ocean/dark/gold travel lifestyle imagery.
- Responsive split-panel layout on desktop.
- Single-column layout on mobile.

Important note:

- Login/registration colors currently use ocean/gold branding, while dashboard/admin CSS uses green/red/purple/dark branding. Future UI work should either preserve this distinction or intentionally unify it across all screens.

# IMPORTANT DEVELOPMENT RULES

## Preservation Rules

- Never break existing functionality.
- Preserve the `index.php?page=...` member routing structure.
- Preserve the `admin/index.php?page=...` admin routing structure.
- Preserve current table names and columns unless a migration is explicitly requested.
- Preserve existing login, registration, package code, product code, bonus, and payout flows.
- Preserve XAMPP compatibility.
- Preserve Bootstrap CDN usage.
- Preserve mobile responsiveness.
- Preserve modular PHP includes.

## Compensation Safety Rules

- All earnings must pass through `bonus_ledger`.
- All deductions must pass through `bonus_ledger`.
- Use transactions for bonus computations.
- Prevent duplicate bonus entries.
- Keep bonus logic auditable.
- Use stable bonus `type` values.
- Keep source relationships traceable.
- Do not directly edit wallet balances because no wallet balance column exists.
- Wallet balance is the sum of `bonus_ledger.amount`.

## Routing Safety Rules

- Do not include arbitrary file paths from `$_GET`.
- Continue using whitelist `switch` routing.
- If adding a new page, add it to:
- the correct router switch
- the matching sidebar navigation if needed
- the appropriate `pages/` or `admin/pages/` folder

## Database Safety Rules

- Use prepared statements for all dynamic input.
- Validate numeric IDs with `intval()`.
- Validate money values with numeric checks.
- Use `htmlspecialchars()` when outputting database values.
- Do not assume foreign keys exist.
- When adding new tables, keep old pages compatible.
- When adding new columns, make them nullable or provide defaults unless all inserts are updated.

## Current Known Gaps To Respect

- `leadership_ranks` table does not currently exist.
- `global_pool` table does not currently exist.
- `settings` table does not currently exist.
- Global pool is display-only.
- Leadership rank logic only computes L1.
- Chairman Bonus qualification logic has not been implemented yet.
- Payouts have no status/approval workflow.
- Some multi-step write flows do not yet use transactions.
- Duplicate bonus prevention is not fully enforced by unique database constraints.

# FUTURE FEATURE INTEGRATION NOTES

## General Integration Approach

Future compensation features should be added carefully by following this sequence:

1. Identify the trigger event.
2. Identify the source record that proves the event happened.
3. Identify eligible members.
4. Calculate amounts.
5. Start a transaction.
6. Check whether the same bonus was already paid.
7. Insert detailed audit rows if needed.
8. Insert wallet rows into `bonus_ledger`.
9. Commit the transaction.
10. Display the new records in member/admin pages.

## Adding New Bonus Types

When adding a new bonus:

- Create a clear bonus `type`.
- Add helper functions in `functions.php` if logic is shared.
- Use prepared statements.
- Use a transaction.
- Add duplicate checks.
- Insert into `bonus_ledger`.
- Add a member page or section if members need to see details.
- Add an admin report or ledger filter if admins need oversight.

Recommended bonus type format:

```text
lowercase_words_with_underscores
```

Examples:

- `leadership_bonus`
- `global_pool`
- `matching_bonus`
- `rank_reward`

## Improving Registration Safety

Registration currently performs multiple writes:

- Insert member.
- Update package code.
- Insert direct bonus.
- Insert generation bonuses.

Future improvements should wrap this entire flow in a transaction.

Recommended duplicate protections:

- Lock or recheck package code status during transaction.
- Add source reference fields to bonus ledger or a separate bonus details table.
- Ensure one direct bonus per new member.
- Ensure one generation bonus per new member/upline/level.

## Improving Product Encoding Safety

Product encoding currently performs multiple writes:

- Insert product purchase.
- Update product code.
- Insert community bonus ledger rows.
- Insert bonus ledger rows.

Future improvements should wrap this entire flow in a transaction.

Recommended duplicate protections:

- Lock or recheck product code status during transaction.
- Ensure one product purchase per used product code.
- Ensure one community bonus per product code/upline/level.

## Adding Leadership Rank Storage

If leadership rank persistence is needed, avoid replacing current computed pages immediately.

Safe approach:

- Add `leadership_ranks` table for rank definitions.
- Optionally add `member_rank_history` table for achievements.
- Keep current computed display working.
- Add admin tools to recalculate ranks.
- Insert rank bonuses into `bonus_ledger` only if rank achievement has not already been rewarded.

## Adding Global Pool Distribution

Global pool is currently display-only. To make it payable:

- Add a pool batch table.
- Define pool period start and end dates.
- Store total package sales for the period.
- Store pool percentage.
- Store calculated pool amount.
- Store qualified member count.
- Store share per member.
- Store distribution status.
- Insert one `bonus_ledger` row per qualified member.
- Prevent rerunning the same period.

Recommended duplicate prevention:

- Unique key on pool period.
- Unique key on member/pool batch distribution.

## Adding Settings Persistence

Settings are currently hardcoded. To make settings editable:

- Add a `settings` table.
- Store settings by key/value.
- Provide defaults when a setting does not exist.
- Validate setting values before saving.
- Cache settings only if needed.
- Update bonus logic to read settings through helper functions.

Possible setting keys:

- `system_name`
- `payout_fee_percent`
- `payout_flat_fee`
- `community_bonus_per_quantity`
- `community_bonus_levels`
- `global_pool_percent`

## Adding Payout Approval

Payouts currently insert immediately and deduct wallet immediately.

If adding approval:

- Add `status` column to `payouts`.
- Suggested statuses: `pending`, `approved`, `rejected`, `paid`.
- Decide whether to deduct on request or approval.
- If deducting on request, refund rejected payouts through a positive `bonus_ledger` row.
- If deducting on approval, ensure balance is still available at approval time.
- Keep all payout-related wallet movements in `bonus_ledger`.

## Adding Admin Security

Admin login is currently hardcoded.

Future secure approach:

- Add an `admins` table.
- Store hashed admin passwords.
- Use `password_hash()` and `password_verify()`.
- Add role or permission columns if needed.
- Replace hardcoded credentials only after migration.
- Preserve `$_SESSION['admin_id']` and `$_SESSION['admin_username']` compatibility.

## Adding Database Constraints

The current schema does not use foreign keys.

If constraints are added:

- Audit existing data first.
- Add indexes before foreign keys.
- Ensure delete/update behavior is safe.
- Avoid cascading deletes on financial records.
- Prefer restricting deletes for members, bonuses, payouts, product purchases, and code usage records.

Recommended indexes:

- `members.username`
- `members.sponsor_id`
- `members.package_id`
- `package_codes.code`
- `package_codes.status`
- `package_codes.used_by_member_id`
- `product_codes.code`
- `product_codes.status`
- `product_codes.used_by_member_id`
- `product_purchases.member_id`
- `bonus_ledger.member_id`
- `bonus_ledger.type`
- `community_bonus_ledger.member_id`
- `community_bonus_ledger.from_member_id`
- `payouts.member_id`

## Future Prompt Guidance For Codex

Before modifying this project, Codex should:

- Read `PROJECT_CONTEXT.md`.
- Inspect the exact target files.
- Preserve the current include/router structure.
- Use MySQLi prepared statements.
- Keep all wallet changes in `bonus_ledger`.
- Use transactions for multi-table compensation changes.
- Add duplicate checks for bonuses.
- Keep member and admin screens responsive.
- Return complete updated files when code is requested.
- Avoid introducing frameworks, Composer, npm, or build tooling unless explicitly requested.
