# Validasi Docker setelah integrasi Redis

Branch: `container/adithya`, berdasarkan main `9b19664` (merge PR #7).
Pemilik bagian: Adithya / Container Engineer.

## Perubahan

- Healthcheck image runtime dan Compose menjalankan `docker/healthcheck.php`:
  HTTP `/health` harus menunjukkan database up, cache Laravel harus Redis,
  dan koneksi cache Redis harus dapat merespons PING. Endpoint backend tidak diubah.
- Redis database default 0 dan database cache 1 dituliskan eksplisit pada Compose
  dan `.env.example`. Host Redis tetap nama service `redis`, client `phpredis`.
- `docker/smoke.php` menguji runtime dengan MySQL dan Redis nyata, tanpa PHPUnit.
  Script membuat alias unik, membuktikan cache dan TTL, kemudian menghapus hanya
  baris/key miliknya. Tidak menjalankan migrate:fresh atau cache:clear.
- Direktori laporan validasi dikecualikan dari build context.

## Menjalankan ulang

Gunakan stack pengujian, bukan database produksi. Environment lokal validasi
berada di luar repo (`C:\Pendekin_Container_Engineer\docker-validation.env`),
berisi kredensial acak dan port host 18081. Jangan commit file tersebut.

```powershell
docker build --target test -t pendekin:test-validation .
docker run --rm pendekin:test-validation php artisan test
docker compose -p pendekin-validation --env-file C:\Pendekin_Container_Engineer\docker-validation.env config --quiet
docker compose -p pendekin-validation --env-file C:\Pendekin_Container_Engineer\docker-validation.env pull app
docker compose -p pendekin-validation --env-file C:\Pendekin_Container_Engineer\docker-validation.env up -d --wait --wait-timeout 240
docker compose -p pendekin-validation --env-file C:\Pendekin_Container_Engineer\docker-validation.env exec -T app php docker/smoke.php
docker compose -p pendekin-validation --env-file C:\Pendekin_Container_Engineer\docker-validation.env ps
curl.exe -i http://localhost:18081/health
```

Tag ini khusus source main 9b19664 ditambah perubahan Docker pada branch ini;
`latest` tidak ditimpa. Compose tetap dikonfigurasi memakai image Docker Hub dan
pull_policy always, tanpa build lokal. Image telah dipublikasikan ke Docker Hub,
ditarik ulang melalui Compose, dan dijalankan tanpa override --pull never.
Digest registry terverifikasi:
`sha256:ca384721bb733d9b34182e27a9c9d2fc5d20348c50705877a0655e396730def2`.
Volume dan network Compose mengikuti project `pendekin-validation`, terpisah dari
stack lama `pendekin`. Port internal app tetap 8000; MySQL/Redis tidak mempublikasikan
port host dan hanya memakai network backend internal.

## Yang harus dibuktikan

1. Suite aplikasi dan subset RedirectTest lulus; suite default memakai cache array.
2. API membuat link (201) dan redirect aktif menghasilkan 302 dengan Location benar.
3. Setelah target di MySQL berubah, request berikut tetap memakai cache Redis lama.
4. Key benar-benar tersimpan di database Redis 1 dengan TTL maksimal 60 detik.
5. Setelah TTL habis, request memuat target baru dari MySQL.
6. Kode tidak ada 404, nonaktif 410, kedaluwarsa 410.
7. Healthcheck gagal jika Redis berhenti dan pulih setelah Redis dinyalakan kembali.
8. Seluruh service healthy dan log tidak menunjukkan error integrasi pada stack uji.

Pengujian gangguan hanya dilakukan pada service Redis project validasi. Tidak
mengubah controller, model, routes, konfigurasi backend, atau Jenkinsfile.

## Hasil

Pengujian langsung pada Docker Desktop, 22 November 2026:

| Pemeriksaan | Hasil |
| --- | --- |
| Main terbaru / branch | Main 9b19664, branch container/adithya |
| Compose config --quiet | Lulus dengan environment validasi terpisah |
| Build stage test | Berhasil, suite benar-benar dieksekusi: 15 passed, 26 assertions |
| Subset RedirectTest di image test | 5 passed, 9 assertions (cache array, bukan bukti Redis nyata) |
| Build runtime dan frontend | Berhasil |
| Smoke HTTP/MySQL/Redis | 14 PASS; lihat smoke-results.txt |
| Cache / TTL | Key di Redis database cache 1, TTL 1..60 detik, benar-benar kedaluwarsa |
| Cache hit / refresh | Target lama tetap dipakai setelah DB berubah; setelah TTL habis target baru dimuat |
| Status HTTP | Create 201, redirect 302 + Location, missing 404, inactive/expired 410 |
| Gangguan Redis | Redis dihentikan; script healthcheck exit 1, sedangkan /health tetap 200/database up |
| Pemulihan Redis | Redis dinyalakan; healthcheck exit 0 dan semua service kembali healthy |
| Konfigurasi runtime | User www-data, restart unless-stopped pada ketiga service |
| Network / port | Backend internal=true; hanya app mempublikasikan port 18081 |
| Data smoke test | Tidak ada fixture docker-% yang tertinggal |
| Log | Migrasi dan startup berhasil; tidak ditemukan error koneksi saat operasi normal |
| Publikasi / registry pull tag baru | Push berhasil; compose pull app dan compose up tanpa --pull never berhasil; semua service healthy |

MySQL mengeluarkan peringatan startup bawaan untuk sertifikat CA self-signed dan
lokasi pid-file; keduanya tidak menghalangi readiness atau koneksi aplikasi.
Log Redis berhenti/mulai berasal dari uji gangguan yang disengaja dan telah pulih.
Status akhir ketiga service tercatat di compose-status.txt. Endpoint host validasi:
http://localhost:18081/health, HTTP 200 dengan {"status":"ok","database":"up"}.

Tidak ada perubahan pada app/, routes/, config/, tests/, composer.json,
composer.lock, atau Jenkinsfile. Hanya referensi DOCKERHUB_IMAGE pada .env lokal
diarahkan ke tag yang dipublikasikan; kredensial tetap dipertahankan. .env tidak
dilacak Git. Commit kode Docker: 6510ff2 pada branch container/adithya.

## Publikasi image

Image terpublikasi: `adithyaananda/pendekin:docker-validation-9b19664`.
Tag latest tidak ditimpa. Untuk menerbitkan perubahan source berikutnya, gunakan
nama tag baru, build target runtime, push, lalu sesuaikan DOCKERHUB_IMAGE sebelum
compose pull app dan compose up. Jangan menggunakan image lama dengan Compose
baru karena healthcheck baru membutuhkan docker/healthcheck.php dalam image.

Bukti registry, health, dan konfigurasi akhir tercatat di registry-results.txt.
