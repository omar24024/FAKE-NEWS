#!/usr/bin/env bash
# Configure MySQL/MariaDB pour la plateforme fake news.
# Usage : sudo bash database/setup_mysql.sh
set -euo pipefail

DB_NAME="fake_news_platform"
DB_USER="fakenews"
DB_PASS="FakeNews_Kali2024"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$PROJECT_ROOT/.env"
SCHEMA="$SCRIPT_DIR/schema.sql"

if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
  echo "❌ Lancez avec sudo :"
  echo "   sudo bash database/setup_mysql.sh"
  exit 1
fi

echo "▶ Création base + utilisateur MySQL…"

mysql <<EOF
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF

echo "▶ Import du schéma…"
if [[ -f "$SCHEMA" ]]; then
  mysql "$DB_NAME" < "$SCHEMA"
  echo "✓ Schéma importé"
else
  echo "⚠ schema.sql introuvable — base créée sans tables"
fi

echo "▶ Mise à jour .env…"
if [[ -f "$ENV_FILE" ]]; then
  sed -i "s/^DB_USER=.*/DB_USER=${DB_USER}/" "$ENV_FILE"
  sed -i "s/^DB_PASS=.*/DB_PASS=${DB_PASS}/" "$ENV_FILE"
  sed -i "s/^DB_NAME=.*/DB_NAME=${DB_NAME}/" "$ENV_FILE"
else
  cat > "$ENV_FILE" <<ENVEOF
DB_HOST=localhost
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}
DB_NAME=${DB_NAME}
DB_CHARSET=utf8mb4
APP_URL=http://127.0.0.1:8080
PYTHON_EXECUTABLE=/home/kali/gbert_project/venv/bin/python3
AI_MODEL=gbert-hassaniya
GBERT_MODEL_PATH=/home/kali/gbert_project/gbert_hassaniya_FINAL.pt
AI_SERVER_URL=http://127.0.0.1:8765
AI_AUTO_ANALYZE=true
ENVEOF
  chown "${SUDO_USER:-kali}:${SUDO_USER:-kali}" "$ENV_FILE" 2>/dev/null || true
fi

echo ""
echo "✅ MySQL configuré !"
echo "   Base      : ${DB_NAME}"
echo "   Utilisateur: ${DB_USER}"
echo "   Mot de passe: ${DB_PASS}"
echo ""
echo "→ Rechargez http://127.0.0.1:8080/login.php"
echo "   Login: admin / admin123"
