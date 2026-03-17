#!/bin/sh
# scripts/init-minio.sh
# Crea los 6 buckets de MinIO y los configura como públicos (lectura anónima).
# Ejecutar UNA VEZ después del primer deploy cuando MinIO ya está corriendo.
#
# Uso (desde el servidor, con los contenedores levantados):
#   docker compose exec minio sh /scripts/init-minio.sh
#
# Corre DENTRO del contenedor minio:
#   - mc ya está incluido en la imagen minio/minio:latest
#   - MINIO_ROOT_USER y MINIO_ROOT_PASSWORD ya son variables de entorno del contenedor
#   - El endpoint es localhost:9000 (mismo contenedor)
# ─────────────────────────────────────────────────────────────────────────────
set -eu

ALIAS="rs"

# ── Registrar alias apuntando a localhost (estamos dentro del contenedor) ─────
echo "Configurando alias '$ALIAS'..."
mc alias set "$ALIAS" "http://localhost:9000" "$MINIO_ROOT_USER" "$MINIO_ROOT_PASSWORD" --api S3v4

# ── Crear buckets y aplicar política pública ──────────────────────────────────
for BUCKET in elementos colegios cursos kits despachos documentos; do
    if mc ls "$ALIAS/$BUCKET" >/dev/null 2>&1; then
        echo "  Bucket '$BUCKET' ya existe — omitiendo creacion"
    else
        echo "  Creando bucket '$BUCKET'..."
        mc mb "$ALIAS/$BUCKET"
        echo "  Bucket '$BUCKET' creado"
    fi

    mc anonymous set download "$ALIAS/$BUCKET"
    echo "  Politica publica aplicada: $BUCKET"
done

echo ""
echo "MinIO inicializado. Buckets disponibles:"
mc ls "$ALIAS"
