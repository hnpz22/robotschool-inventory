#!/usr/bin/env bash
# scripts/init-minio.sh
# Crea los 6 buckets de MinIO y los configura como públicos (lectura anónima).
# Ejecutar UNA VEZ después del primer deploy cuando MinIO ya está corriendo.
#
# Uso:
#   bash scripts/init-minio.sh
#
# Requiere: mc (MinIO Client) disponible en el PATH, o se ejecuta dentro del
#           contenedor app donde mc se instala desde este mismo script.
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

# ── Leer variables del entorno (ya deben estar exportadas o en .env) ──────────
MINIO_ENDPOINT="${MINIO_ENDPOINT:-http://minio:9000}"
MINIO_ROOT_USER="${MINIO_ROOT_USER:-robotschool}"
MINIO_ROOT_PASSWORD="${MINIO_ROOT_PASSWORD:?'ERROR: MINIO_ROOT_PASSWORD no está definida'}"

BUCKETS=(
    "${MINIO_BUCKET_ELEMENTOS:-elementos}"
    "${MINIO_BUCKET_COLEGIOS:-colegios}"
    "${MINIO_BUCKET_CURSOS:-cursos}"
    "${MINIO_BUCKET_KITS:-kits}"
    "${MINIO_BUCKET_DESPACHOS:-despachos}"
    "${MINIO_BUCKET_DOCUMENTOS:-documentos}"
)

ALIAS="rs"  # alias local de mc para este servidor

# ── Instalar mc si no está disponible ────────────────────────────────────────
if ! command -v mc &>/dev/null; then
    echo "▶ mc no encontrado. Descargando MinIO Client..."
    curl -sSL "https://dl.min.io/client/mc/release/linux-amd64/mc" -o /usr/local/bin/mc
    chmod +x /usr/local/bin/mc
    echo "✓ mc instalado en /usr/local/bin/mc"
fi

# ── Registrar alias del servidor ──────────────────────────────────────────────
echo "▶ Configurando alias '$ALIAS' → $MINIO_ENDPOINT"
mc alias set "$ALIAS" "$MINIO_ENDPOINT" "$MINIO_ROOT_USER" "$MINIO_ROOT_PASSWORD" --api S3v4

# ── Crear buckets y aplicar política pública ──────────────────────────────────
for BUCKET in "${BUCKETS[@]}"; do
    if mc ls "$ALIAS/$BUCKET" &>/dev/null; then
        echo "  ⚠ Bucket '$BUCKET' ya existe — omitiendo creación"
    else
        echo "  ▶ Creando bucket '$BUCKET'..."
        mc mb "$ALIAS/$BUCKET"
        echo "  ✓ Bucket '$BUCKET' creado"
    fi

    echo "  ▶ Aplicando política pública de descarga en '$BUCKET'..."
    mc anonymous set download "$ALIAS/$BUCKET"
    echo "  ✓ Política aplicada: $BUCKET"
done

echo ""
echo "✅ MinIO inicializado. Buckets disponibles:"
mc ls "$ALIAS"
