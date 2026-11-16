// Pendekin (K01) - CI/CD Pipeline
// Alur: Checkout -> Test -> Build Image -> Push Image -> Deploy -> Smoke Test
//
// Yang harus ada di Jenkins (Manage Jenkins > Credentials):
//   dockerhub-credentials : Username with password (password = access token Docker Hub)
//   pendekin-env          : Secret file, isinya file .env asli (salin dari .env.example)
//
// Agent memakai `sh` (Jenkins di container/Linux). Perintah docker dan
// docker compose harus bisa dijalankan oleh user Jenkins.

pipeline {
    agent any

    // Build dipicu webhook GitHub (Payload URL: <url-ngrok>/github-webhook/).
    triggers {
        githubPush()
    }

    options {
        skipDefaultCheckout(true)   // checkout hanya sekali, di stage Checkout
        disableConcurrentBuilds()   // dua deploy tidak boleh berjalan bersamaan
        timeout(time: 60, unit: 'MINUTES')
    }

    environment {
        IMAGE = 'keyshaap01/pendekin'
        TAG   = "${env.BUILD_NUMBER}"

        // Nama project Compose dibuat tetap supaya nama container, network, dan
        // volume tidak ikut nama folder workspace Jenkins.
        COMPOSE_PROJECT_NAME = 'pendekin'

        // Kode link contoh dari seeder untuk cek redirect 302 (tantangan K01).
        // Selama masih kosong, seeder dan cek 302 dilewati. Isi (mis. 'contoh')
        // setelah fitur redirect dan seeder link contoh sudah di-merge ke main.
        SMOKE_CODE = ''
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
                sh 'git log -1 --oneline'
            }
        }

        // Quality gate: stage `test` di Dockerfile menjalankan `php artisan test`.
        // Kalau ada test gagal, docker build gagal, stage ini merah, dan stage
        // sesudahnya tidak dijalankan sehingga versi lama tetap berjalan.
        stage('Test') {
            steps {
                sh 'docker build --target test -t "$IMAGE:test" .'
            }
        }

        // Dua tag: nomor build (jejak versi) dan latest (yang dipakai compose.yaml).
        stage('Build Image') {
            steps {
                sh 'docker build --target runtime -t "$IMAGE:$TAG" -t "$IMAGE:latest" .'
            }
        }

        stage('Push Image') {
            steps {
                // Login di dalam withCredentials, push di luarnya, supaya nama
                // image di console log tidak ikut disensor menjadi ****.
                withCredentials([usernamePassword(credentialsId: 'dockerhub-credentials',
                                                  usernameVariable: 'DOCKER_USER',
                                                  passwordVariable: 'DOCKER_PASS')]) {
                    sh 'echo "$DOCKER_PASS" | docker login -u "$DOCKER_USER" --password-stdin'
                }
                sh 'docker push "$IMAGE:$TAG"'
                sh 'docker push "$IMAGE:latest"'
            }
        }

        stage('Deploy') {
            steps {
                withCredentials([file(credentialsId: 'pendekin-env', variable: 'ENV_FILE')]) {
                    sh '''
                        # Variabel shell menang atas isi .env, jadi image yang
                        # di-deploy pasti image yang baru saja di-push.
                        export DOCKERHUB_IMAGE="$IMAGE:latest"

                        docker compose --env-file "$ENV_FILE" pull app
                        # --wait: tunggu sampai mysql, redis, dan app berstatus healthy.
                        docker compose --env-file "$ENV_FILE" up -d --remove-orphans --wait --wait-timeout 300

                        if [ -n "${SMOKE_CODE:-}" ]; then
                            # Seeder harus idempotent (updateOrCreate) karena jalan tiap deploy.
                            docker compose --env-file "$ENV_FILE" exec -T app php artisan db:seed --force
                        fi

                        docker compose --env-file "$ENV_FILE" ps
                    '''
                }
            }
        }

        stage('Smoke Test') {
            steps {
                // Request dikirim dari container curl yang ditempel ke network
                // frontend, jadi tetap jalan walau Jenkins sendiri ada di dalam
                // container (di sana `localhost` bukan host tempat app berjalan).
                sh '''
                    set +x
                    NET="${COMPOSE_PROJECT_NAME}_frontend"
                    BASE="http://app:8000"

                    # Mencetak "<status HTTP> <tujuan redirect>" tanpa mengikuti redirect.
                    probe() {
                        docker run --rm --network "$NET" curlimages/curl:latest \
                            -s -o /dev/null --max-time 10 \
                            -w '%{http_code} %{redirect_url}' "$1" || true
                    }

                    echo "== Smoke test 1: GET /health harus 200 =="
                    code=""
                    attempt=1
                    while [ "$attempt" -le 12 ]; do
                        result=$(probe "$BASE/health")
                        code=${result%% *}
                        echo "Percobaan $attempt: GET /health -> HTTP $code"
                        if [ "$code" = "200" ]; then
                            break
                        fi
                        attempt=$((attempt + 1))
                        sleep 5
                    done
                    if [ "$code" != "200" ]; then
                        echo "SMOKE TEST GAGAL: /health membalas HTTP '$code' (harus 200)"
                        exit 1
                    fi
                    docker run --rm --network "$NET" curlimages/curl:latest -s --max-time 10 "$BASE/health"
                    echo

                    if [ -n "${SMOKE_CODE:-}" ]; then
                        echo "== Smoke test 2: GET /$SMOKE_CODE harus 302 =="
                        result=$(probe "$BASE/$SMOKE_CODE")
                        code=${result%% *}
                        target=${result#* }
                        echo "GET /$SMOKE_CODE -> HTTP $code -> $target"
                        if [ "$code" != "302" ]; then
                            echo "SMOKE TEST GAGAL: /$SMOKE_CODE membalas HTTP '$code' (harus 302)"
                            exit 1
                        fi
                        echo "== Isi Redis setelah redirect (bukti cache kode -> URL) =="
                        docker exec "${COMPOSE_PROJECT_NAME}-redis-1" redis-cli info keyspace || true
                    else
                        echo "PERINGATAN: SMOKE_CODE kosong, cek redirect 302 belum dijalankan."
                    fi

                    echo "SMOKE TEST LOLOS"
                '''
            }
            post {
                failure {
                    sh 'docker logs --tail 60 "${COMPOSE_PROJECT_NAME}-app-1" || true'
                }
            }
        }
    }

    post {
        always {
            sh 'docker logout || true'
            sh 'docker image prune -f || true'
        }
        success {
            echo "PIPELINE SUKSES: ${env.IMAGE}:${env.TAG} sudah di-push ke Docker Hub, di-deploy, dan lolos smoke test."
        }
        failure {
            echo 'PIPELINE GAGAL: cek stage yang merah di Stage View. Kalau gagal sebelum Deploy, versi lama tetap berjalan.'
        }
    }
}
