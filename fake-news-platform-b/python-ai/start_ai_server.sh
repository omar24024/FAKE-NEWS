#!/usr/bin/env bash
# Demarre le serveur IA GBERT (modele charge une seule fois en memoire)
set -euo pipefail

DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(dirname "$DIR")"

# Charger .env si present
if [[ -f "$PROJECT_ROOT/.env" ]]; then
  set -a
  # shellcheck disable=SC1091
  source <(grep -v '^\s*#' "$PROJECT_ROOT/.env" | grep -v '^\s*$' | sed 's/\r$//')
  set +a
fi

PYTHON="${PYTHON_EXECUTABLE:-python3}"
HOST="${AI_SERVER_HOST:-127.0.0.1}"
PORT="${AI_SERVER_PORT:-8765}"

cd "$DIR"
echo "Serveur IA GBERT — http://${HOST}:${PORT}"
echo "Python : $PYTHON"
exec "$PYTHON" -m uvicorn ai_server:app --host "$HOST" --port "$PORT"
