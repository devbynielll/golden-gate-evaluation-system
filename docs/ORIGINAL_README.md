Historical upstream README. Preserve attribution/context; its seeding and development instructions are NOT the deployment procedure for this repository.

# Anonymous Monitoring and Evaluation System

Golden Gate Colleges faculty/office evaluation system. Laravel 12 + Inertia + React.

 Requirements

 PHP 8.2+
 Composer
 Node 18+ and npm
 MySQL

------------------------------------------------------- Setup

1. Install dependencies:
   
   composer install
   npm install
   

2. Copy the env file and generate an app key:
   
   cp .env.example .env
   php artisan key:generate
   

3. Create a MySQL database

4. Edit `.env`:
    `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` —  MySQL credentials

    `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` — an SMTP account for sending evaluation emails (Gmail needs an [app password](https://myaccount.google.com/apppasswords), not regular password)

    `ADMIN_EMAIL`, `ADMIN_ID`, `ADMIN_PASSWORD` — the default admin account created by the seeder. `ADMIN_PASSWORD` must have uppercase, lowercase, a number, and a symbol.

5. Run migrations and seed the admin account:
   
   php artisan migrate
   php artisan db:seed
   

6. Link storage:
   
   php artisan storage:link
   

------------------------------------------------------- Run

Two terminals, both need to stay open:


php artisan serve
npm run dev


Open the URL `php artisan serve` prints (usually `http://localhost:8000`).