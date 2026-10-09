# pinvest-app — অগর/উদ ব্যবসা ব্যবস্থাপনা

Partner-ভিত্তিক অগর/উদ ব্যবসার হিসাব: cycle (সাইকেল) খুলে বিক্রি/ক্রয়/খরচ/উৎপাদন ট্র্যাক করে, cycle বন্ধ করলে **COGS-ভিত্তিক আসল লাভ** বের হয় — লাভ হলে partner-রা commission পায়, owner (investor) বড় অংশ নেয়। মজুদ (stock), নগদ, রিপোর্ট সব auto হিসাব হয়। বিস্তারিত: **[USER_MANUAL.md](USER_MANUAL.md)**

## কারা ব্যবহার করবে

- **Owner (Investor):** সবকিছু দেখে ও অনুমোদন (approve) করে, cycle open/close করে, টাকা তোলে
- **Partner:** ক্রয়/খরচ/বিক্রি/উৎপাদন entry দেয়, নিজের commission দেখে ও উত্তোলন request পাঠায়

## চালু করার নিয়ম

```bash
composer install
php artisan migrate:fresh --seed     # default অ্যাকাউন্ট + বাংলা master data (ডেমো-সহ প্রথমবার)
php artisan serve                    # অথবা Laragon-এ সাইট চালু থাকলে শুধু migrate:fresh --seed
```

## Default অ্যাকাউন্ট (password সবার `123456`)

| কে | নম্বর | ভূমিকা |
|---|---|---|
| Nazmul | 01675870047 | Owner / Investor |
| Raju | 01747666533 | Partner |
| Sahel | 01705752545 | Partner |
| Riad | 01641196743 | Partner |

> এগুলো শুধু **DefaultDataSeeder** থেকে আসে। অ্যাকাউন্ট/নম্বর বদলাতে হলে `database/seeders/DefaultDataSeeder.php` বদলে উপরের কমান্ড আবার চালান।

## Fresh start — সব transaction মুছে নতুন করে

```bash
# সব মুছে শুধু default (মালিক+পার্টনার+বাংলা আইটেম) রাখে, ডেমো আসে না:
php artisan migrate:fresh --seeder=DefaultDataSeeder

# ডেমো ডেটাসহ (অনুশীলনের জন্য):
php artisan migrate:fresh --seed
```

⚠️ `migrate:fresh` সব ডেটা **স্থায়ীভাবে মুছে** — আগে ব্যাকআপ নিন (`database/database.sqlite` কপি করুন)।

## গুরুত্বপূর্ণ নোট

- Fresh শুরুর পর **Partners page থেকে partner-দের commission rate সেট করতে ভুলবেন না** — rate 0 থাকলে কমিশন হিসাব হয় না
- প্রত্যেকে প্রথম লগইনে **Profile → পাসওয়ার্ড বদলান**
- নতুন style যোগ করলে: `npm run build` (Tailwind CDN ব্যবহার করা হয়, সাধারণত দরকার হয় না)

## ডকুমেন্টেশন

- **[USER_MANUAL.md](USER_MANUAL.md)** — সম্পূর্ণ ব্যবহারবিধি (বাংলা): cycle system, entry, উৎপাদন, কমিশন-উত্তোলন, রিপোর্ট, সমস্যা-সমাধান
