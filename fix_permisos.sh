#!/bin/bash
# ============================================================
#  ROBOTSchool — Fix permisos macOS XAMPP
#  Ejecutar en Terminal: bash fix_permisos.sh
#  O con sudo si pide contraseña: sudo bash fix_permisos.sh
# ============================================================

RUTA="/Applications/XAMPP/xamppfiles/htdocs/robotschool_inventory"

echo "🔧 Aplicando permisos en: $RUTA"

# Carpetas: lectura + ejecución para todos, escritura para dueño
find "$RUTA" -type d -exec chmod 755 {} \;

# Archivos PHP/HTML/CSS/JS: lectura para todos
find "$RUTA" -type f \( -name "*.php" -o -name "*.html" -o -name "*.css" -o -name "*.js" \) -exec chmod 644 {} \;

# Carpeta uploads: escritura necesaria para subir imágenes
chmod -R 775 "$RUTA/assets/uploads"

# Asegurarse de que el usuario de Apache (daemon) pueda leer
# En macOS XAMPP el usuario de Apache es 'daemon'
chown -R $(whoami):daemon "$RUTA" 2>/dev/null || true

echo "✅ Permisos aplicados correctamente."
echo ""
echo "Si el error persiste, ejecuta en Terminal:"
echo "  sudo chmod -R 755 $RUTA"
echo "  sudo chown -R daemon:daemon $RUTA"
