# Panduan container Pendekin (Kelompok 1)

Perubahan masih lokal pada branch `container/adithya`. Belum commit atau push.

## Hasil pemeriksaan

- Permintaan Fandi: Dockerfile multi-stage dengan stage test, Compose memakai image Docker Hub, MySQL dengan volume dan healthcheck, depends_on service_healthy, restart unless-stopped, network backend internal, dan contoh environment tanpa rahasia.
- Repo aktual menggunakan Laravel ^13.17 dan PHP ^8.3. Dependency Symfony pada composer.lock meminta PHP >=8.4.1, sehingga Dockerfile memakai PHP 8.4. Jangan ganti ke 8.2 tanpa penyesuaian dependency oleh backend.
- Test menggunakan SQLite in-memory; Dockerfile menyediakan pdo_sqlite untuk test dan pdo_mysql untuk runtime.
- Redis dipertahankan dari konfigurasi lokal yang sudah ada. Container Redis tidak membuktikan fitur cache redirect telah diimplementasikan backend. Klaim kewajiban Redis dari PDF belum diverifikasi ulang pada sesi ini.
- App memakai frontend dan backend; MySQL/Redis hanya backend internal dan tidak mempublikasikan port ke host.
- Compose memakai pull_policy always supaya menjalankan image registry. Docker Hub harus sudah berisi image tersebut sebelum up.
- .env tidak terlacak dan diabaikan Git. Jangan menyalin password nyata ke .env.example.

## 1. Masuk folder project

```powershell
cd C:\Pendekin_Container_Engineer\Pendekin
git status --short
git branch --show-current
```

Branch saat diperiksa adalah container/adithya. Tidak perlu git pull ketika perubahan lokal belum disimpan.

## 2. Lengkapi .env yang sudah ada

Jangan menimpa .env dengan Copy-Item. Edit file yang sudah ada dan gunakan nilai berikut dengan nama image serta password milik tim:

```dotenv
DOCKERHUB_IMAGE=adithyaananda/pendekin:latest
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost:18080
APP_PORT=18080
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=pendekin
DB_USERNAME=pendekin
DB_PASSWORD=ISI_PASSWORD_DATABASE
MYSQL_ROOT_PASSWORD=ISI_PASSWORD_ROOT_BERBEDA
```

APP_KEY lokal dipertahankan. MYSQL_ROOT_PASSWORD sudah dibuat otomatis di .env; tidak perlu diganti untuk pengujian pertama. APP_ENV dan APP_DEBUG sudah disesuaikan untuk container. DOCKERHUB_IMAGE sudah diisi adithyaananda/pendekin:latest sesuai konfirmasi tim. DB_HOST=mysql menunjuk service Compose; 127.0.0.1 di container menunjuk container itu sendiri. DB_USERNAME jangan root karena Compose memakai MYSQL_USER untuk membuat user aplikasi.

Jika ini instalasi baru tanpa .env, baru salin .env.example. Bila APP_KEY kosong, generate setelah image runtime dibangun:

```powershell
docker run --rm --entrypoint php $image artisan key:generate --show
```

Salin hasil base64 ke APP_KEY di .env; jangan bagikan key dalam screenshot.

## 3. Pastikan Docker Desktop siap

```powershell
docker version
```

Harus ada bagian Client dan Server. Bila hanya Client atau ada error pipe, jalankan Docker Desktop dengan Linux containers dan tunggu engine siap.

## 4. Jalankan stage test

```powershell
docker build --target test -t pendekin:test .
```

Stage ini memasang dependency development, membuat environment khusus image test, dan menjalankan php artisan test. PHP host 8.2 tidak perlu dipakai. Tunggu build berhasil; jangan menyatakan test lulus sebelum outputnya ada.

## 5. Build image runtime

Nama image berikut sudah sama dengan DOCKERHUB_IMAGE di .env:

```powershell
$image = "adithyaananda/pendekin:latest"
docker build --target runtime -t $image .
```

Stage assets membangun frontend; runtime memasang dependency tanpa paket development dan menjalankan app sebagai www-data. CMD melakukan migrasi lalu menjalankan server Laravel untuk demonstrasi tugas lokal.

## 6. Publikasikan image ke Docker Hub

Langkah ini dilakukan pemilik akun atau pipeline Jenkins Keysha setelah build berhasil:

```powershell
docker login
docker push $image
```

Gunakan tag yang sama di .env. Jika image sudah dipublikasikan Keysha, kamu cukup memakai nama/tag tersebut dan lanjut ke langkah 7. Image privat memerlukan docker login dengan akun yang punya akses.

## 7. Validasi dan jalankan Compose

```powershell
docker compose config --quiet
docker compose pull app
docker compose up -d --wait --wait-timeout 240
docker compose ps
```

config --quiet memvalidasi tanpa menampilkan password. Semua service diharapkan healthy. MySQL diperiksa memakai SELECT 1 dengan user aplikasi; app menunggu MySQL dan Redis healthy. Volume mysql_data mempertahankan data DB.

## 8. Periksa endpoint

```powershell
curl.exe -i http://localhost:18080/health
```

Harus HTTP 200 dengan JSON {"status":"ok","database":"up"}. Jika gagal:

```powershell
docker compose logs --tail=100 app mysql redis
```

Mengubah password .env tidak mengubah user/password database yang sudah tersimpan dalam volume. Jangan hapus volume berisi data penting; selaraskan kredensial dengan database yang sudah diinisialisasi.

## 9. Review tanpa commit

```powershell
git diff --check
git diff -- .env.example
git status --short
git ls-files -- .env
git check-ignore .env
```

ls-files .env harus kosong; check-ignore harus menampilkan .env. File baru belum muncul pada git diff biasa; buka Dockerfile, compose.yaml, .dockerignore, dan panduan ini langsung di editor. Belum perlu git add, commit, atau push.

## Status validasi sesi ini

Compose berhasil divalidasi melalui config --quiet. Berdasarkan output terminal pengguna pada 14 November 2026, image adithyaananda/pendekin:latest berhasil ditarik dari Docker Hub; app, MySQL, dan Redis semuanya healthy. GET http://localhost:18080/health mengembalikan HTTP 200 dengan JSON {"status":"ok","database":"up"}; respons menunjukkan PHP 8.4.27. Startup dan koneksi database dengan demikian telah terbukti pada Docker Desktop pengguna. Pengguna juga berhasil menjalankan docker build --target test -t pendekin:test . dengan status FINISHED. Langkah RUN yang memuat php artisan test menggunakan cache dari build sukses sebelumnya; test tidak dieksekusi ulang pada build tersebut. Jumlah test tidak terlihat pada output build yang dilampirkan. PHP host 8.2 tidak cocok dengan lockfile. Tautan percakapan ChatGPT tidak berhasil dibuka; review berdasarkan file lokal dan output terminal pengguna. Isi PDF tugas belum berhasil diekstrak, sehingga ketentuan dokumen belum dapat dikonfirmasi ulang. Jenkins tidak dikerjakan; belum ada commit atau push kode oleh agen.

## Port lokal

Port host 8000 dan 8080 terpakai saat startup. Port 18080 berhasil diuji bind dari sesi lokal sebelum dipilih. .env lokal memakai APP_PORT=18080 dan APP_URL=http://localhost:18080. Port aplikasi di dalam container tetap 8000; healthcheck tidak perlu diubah. Jalankan ulang docker compose up -d --wait --wait-timeout 240.
