# FECTUM Hellas

The website of FECTUM Hellas, a real estate development and investment company in Athens, Greece: [fectumhellas.gr](https://fectumhellas.gr).

One [FloCMS](https://github.com/hostkurd/FloCMS) application:

- **Website:** server-rendered FloCMS templates in English, Arabic (right to left) and Greek (`/en`, `/ar`, `/el`). Live parts such as the property filters use [flocms-reactive](https://github.com/hostkurd/flocms-reactive).
- **Admin:** [flocms-admin](https://github.com/hostkurd/flocms-admin) at `/admin`. `admin.fectumhellas.gr` redirects there.
- **Database:** MySQL or MariaDB.

The agreed scope, pages and decisions are in the *FECTUM Hellas — Final Website Workflow* document.

## Requirements

- PHP 8.1 or newer with `pdo_mysql`, `mbstring`, `json` and `gd`
- Composer
- MySQL 5.7+ or MariaDB 10.3+

## Local setup

```
git clone https://github.com/hostkurd/fectumhellas.git
cd fectumhellas
composer install
copy .env.example .env
php flo key:generate
```

On macOS or Linux use `cp` instead of `copy`. Create an empty database (utf8mb4), set `DB_NAME`, `DB_USERNAME` and `DB_PASSWORD` in `.env`, then:

```
php flo migrate
php flo user:create --role=super-admin
php flo admin:doctor
php flo serve
```

Open `http://localhost:8000/en/admin` and sign in.

Starting lookups (cities, property types, amenities in three languages; skipped when a table already has rows, so it is safe on the live site):

```
php flo db:seed
```

Sample content for testing (six listings, the development Residence One, an article, two team members, an inquiry). Local or test server only, never the live site:

```
php flo db:seed --class=DemoSeeder
```

## Content in the admin

| Sidebar | URL | Holds |
| --- | --- | --- |
| Inquiries | `/admin/inquiries` | Sent from the website; staff set status, owner and an internal note |
| Properties | `/admin/listings` | Listings, with gallery, floor plans, videos, a YouTube/Vimeo link and a public PDF brochure |
| Developments | `/admin/projects` | Developments, with their residences (listings) and progress updates on the same page |
| Insights | `/admin/articles` | Articles |
| Team | `/admin/team` | Team members on About |
| Cities, Property types, Amenities | `/admin/cities` … | Lookups |
| Pages | `/admin/site-pages` | Home, Services, About, Contact … built from blocks |
| Settings | `/admin/settings` | Contact details, company details, social links, notification address, SEO defaults, cost of buying (Administrators only) |

The resource slugs differ from the public addresses (`/properties`, `/developments`, `/insights`) because a public controller of the same name would also answer `/admin/<slug>`.

A property or development can only be published with its English title and description, a city, a cover photo and (properties) a type and a price or "Price on request". Option lists shared with the website (statuses, energy classes, …) are in `support/Catalog.php`; their labels are the `fh.*` keys of `lang/*.php`.

## Roles

| Role | For | Can do |
| --- | --- | --- |
| Editor | FECTUM Hellas staff | Properties, developments, pages, articles, media, inquiries |
| Administrator | FECTUM Hellas staff | Everything an Editor can, plus users, roles, website settings and the activity log |
| Super Admin | HostKurd developers | Everything |

Roles and permissions are set in `config/config.php` and can be adjusted on the admin's Roles page.

## How we work

1. Each piece of work is built on its own branch and opened as a pull request.
2. The branch is pulled and tested on a local PC or the server.
3. When it is approved, it is merged into `main`.

`composer.lock` pins every package version. Upgrade on purpose with `composer update`, test, and commit the new lock file.

## Deploy (cPanel, over SSH)

The project lives outside `public_html` (e.g. `~/fectumhellas`) and the domain serves only its `public/` folder. On our server the domain's folder is a symlink, created once as the account user:

```
ln -s /home/USER/fectumhellas/public ~/public_html/fectumhellas.gr
```

Remove the link with `rm` (no trailing slash), never with cPanel File Manager. cPanel AutoSSL writes its checks to `public/.well-known/`, which Git ignores.

```
cd ~/fectumhellas
git pull origin main
composer install --no-dev --optimize-autoloader
php flo migrate
php flo view:clear
php flo admin:doctor
```

Take a cPanel backup before a deploy that runs migrations. One cPanel cron job runs the scheduler every minute:

```
* * * * * php /home/USER/fectumhellas/flo schedule:run >> /dev/null 2>&1
```

Kept out of Git: `.env`, `vendor/`, uploaded files (`public/uploads/`, `storage/admin-uploads/`), caches and logs. Media library files are in `public/uploads/editor/`, brochures in `public/uploads/admin/`: include both in backups.

## Brand files

Logos are in `public/images/brand/`:

- `logo-line.svg` and `logo-line-white.svg`: the one-line logo used by the website header and footer, in logo blue (`#2B4285`) and white.
- Wide, stacked and mark only, in navy (`#0e2841`) and white.

The admin uses `#2B4285` as its brand colour (`config/admin.php`).
