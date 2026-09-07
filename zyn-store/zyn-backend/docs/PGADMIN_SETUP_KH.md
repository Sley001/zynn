# របៀបភ្ជាប់ Laravel ជាមួយ PostgreSQL និង pgAdmin 4

## ចំណាំសំខាន់

`pgAdmin 4` គឺជា application សម្រាប់មើល និងគ្រប់គ្រង PostgreSQL។ Database ពិតគឺ
`PostgreSQL` ហើយ Laravel ភ្ជាប់ទៅ PostgreSQL ដោយប្រើ `pdo_pgsql`។

## 1. បង្កើត database

ក្នុង pgAdmin 4៖

1. បើក `Servers > PostgreSQL > Databases`។
2. Right-click `Databases` → `Create` → `Database`។
3. Database name: `zyn_store`។
4. Owner: `postgres`។
5. ចុច `Save`។

ជម្រើសមួយទៀត៖ បើក Query Tool នៅលើ database `postgres` ហើយ run file
`database/sql/create_zyn_store_database.sql`។

## 2. បើក PostgreSQL extensions ក្នុង PHP

បើក `php.ini` ហើយធានាថាបន្ទាត់ទាំងនេះគ្មាន `;` នៅខាងមុខ៖

```ini
extension=pdo_pgsql
extension=pgsql
```

បិទ និងបើក terminal ឡើងវិញ រួចពិនិត្យ៖

```powershell
php -m | findstr pgsql
```

គួរតែឃើញ `pdo_pgsql` និង `pgsql`។

## 3. កំណត់ `.env`

Copy `.env.example` ទៅ `.env` ហើយកែតែ password ពិតរបស់ PostgreSQL៖

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=zyn_store
DB_USERNAME=postgres
DB_PASSWORD=YOUR_POSTGRES_PASSWORD
```

កុំ upload `.env` ទៅ GitHub និងកុំផ្ញើ PostgreSQL password ក្នុង chat។

## 4. បង្កើត tables និង sample data

```powershell
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

បន្ទាប់មកបើក៖

- API health: `http://127.0.0.1:8000/api/health`
- Products: `http://127.0.0.1:8000/api/products`

## 5. មើល tables ក្នុង pgAdmin 4

ចូល៖

`Databases > zyn_store > Schemas > public > Tables`

Right-click `Tables` → `Refresh`។ អ្នកនឹងឃើញ៖

- `products`
- `orders`
- `order_items`
- `payments`
- `users`
- `personal_access_tokens`
- Laravel system tables

## 6. បង្កើត admin account

ដាក់ email និង password ខ្លាំងក្នុង `.env`៖

```env
ADMIN_NAME="Store Admin"
ADMIN_EMAIL="your-email@example.com"
ADMIN_PASSWORD="USE-A-STRONG-UNIQUE-PASSWORD"
```

រួច run៖

```powershell
php artisan db:seed --class=AdminUserSeeder
```

Seeder មិនមាន default admin password ដើម្បីការពារសុវត្ថិភាព។
