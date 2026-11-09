#!/bin/bash
cd "$(dirname "$0")"

php artisan config:clear > /dev/null
php artisan serve --port=8000 > /dev/null 2>&1 &
PID=$!
trap 'kill $PID 2>/dev/null' EXIT
sleep 4

KEY=$(grep '^WA_WEBHOOK_KEY=' .env | cut -d= -f2)

kirim() {
  JSON=$(php -r 'echo json_encode(["sender"=>$argv[1],"message"=>$argv[2]]);' -- "$1" "$2")
  curl -s -X POST "http://127.0.0.1:8000/api/wa/webhook?key=$KEY" \
    -H "Accept: application/json" -H "Content-Type: application/json" \
    -d "$JSON" \
  | php -r '$j = json_decode(file_get_contents("php://stdin")); echo $j->reply ?? ("(tidak ada balasan) " . json_encode($j));'
}

while true; do
  echo
  echo "=== AGROENDER - Chat Tes ==="
  echo "Chat sebagai siapa?"
  echo "1. Petani Contoh"
  echo "2. Pengepul (Pak Budi)"
  echo "3. Admin"
  echo "4. Keluar dari program"
  read -r -p "Pilih 1-4 > " P || break
  case "$P" in
    1) N=6281200000001 ;;
    2) N=6281300000001 ;;
    3) N=6281200000000 ;;
    4) break ;;
    *) echo "Pilih angka 1 sampai 4."; continue ;;
  esac

  LAST=""
  echo
  echo "Ketik pesan lalu Enter. Mulai dengan: halo"
  while true; do
    read -r -p "Anda > " MSG || break 2
    if [ -z "$MSG" ]; then
      echo "(ketik sesuatu dulu, contoh: halo)"
      continue
    fi

    R=$(kirim "$N" "$MSG")
    echo
    echo "Bot > $R"
    echo

    if [ "$MSG" = "9" ] && [[ "$LAST" == *"9. Keluar"* ]]; then
      echo "--- Keluar. Pilih pengguna lain. ---"
      break
    fi
    LAST="$R"
  done
done

echo "Selesai."
