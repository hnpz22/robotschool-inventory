#!/bin/bash
# ============================================================
#  ROBOTSchool — Script de instalación y fix definitivo
#  Ejecutar: sudo bash setup_rbsinventario.sh
# ============================================================

RUTA="/Applications/XAMPP/xamppfiles/htdocs/rbsinventario"

echo ""
echo "======================================================"
echo "  ROBOTSchool Inventory — Setup y Fix de Permisos"
echo "======================================================"
echo ""

# Verificar que existe la carpeta
if [ ! -d "$RUTA" ]; then
  echo "ERROR: No se encontró la carpeta $RUTA"
  echo "Verifica el nombre de la carpeta en htdocs"
  exit 1
fi

echo "1. Aplicando permisos a todos los archivos..."
find "$RUTA" -type d -exec chmod 755 {} \;
find "$RUTA" -type f -exec chmod 644 {} \;

echo "2. Dando permisos de escritura a uploads y logs..."
chmod -R 775 "$RUTA/assets/uploads" 2>/dev/null || true
mkdir -p "$RUTA/assets/uploads"
chmod 775 "$RUTA/assets/uploads"

echo "3. Cambiando propietario a daemon (usuario de Apache en Mac)..."
chown -R daemon:daemon "$RUTA" 2>/dev/null || \
chown -R www:www "$RUTA" 2>/dev/null || \
chown -R _www:_www "$RUTA" 2>/dev/null || \
echo "   (chown no aplicó - puede ser normal en algunas versiones de macOS)"

echo "4. Verificando PHP puede leer los archivos..."
TEST=$(sudo -u daemon cat "$RUTA/index.php" 2>&1 | head -1)
if echo "$TEST" | grep -q "Permission denied"; then
  echo "   WARN: Apache aún no puede leer. Aplicando chmod 755 alternativo..."
  chmod -R 755 "$RUTA"
else
  echo "   OK: Apache puede leer los archivos"
fi

echo ""
echo "5. Creando alias 'rbsfix' para uso futuro..."
SHELL_RC="$HOME/.zshrc"
[ -f "$HOME/.bashrc" ] && SHELL_RC="$HOME/.bashrc"

ALIAS_CMD="alias rbsfix='sudo chmod -R 644 /Applications/XAMPP/xamppfiles/htdocs/rbsinventario/**/*.php && sudo chmod -R 755 /Applications/XAMPP/xamppfiles/htdocs/rbsinventario && echo Permisos OK'"

if ! grep -q "rbsfix" "$SHELL_RC" 2>/dev/null; then
  echo "" >> "$SHELL_RC"
  echo "# ROBOTSchool - Fix permisos XAMPP" >> "$SHELL_RC"
  echo "$ALIAS_CMD" >> "$SHELL_RC"
  echo "   Alias 'rbsfix' creado en $SHELL_RC"
else
  echo "   Alias 'rbsfix' ya existe"
fi

echo ""
echo "======================================================"
echo "  RESULTADO FINAL"
echo "======================================================"
echo ""
echo "Archivos PHP en el proyecto:"
find "$RUTA" -name "*.php" | wc -l | xargs echo " Total:"
echo ""
echo "Permisos de archivos clave:"
ls -la "$RUTA/index.php" 2>/dev/null || echo " index.php no encontrado"
ls -la "$RUTA/config/config.php" 2>/dev/null
ls -la "$RUTA/modules/kits/constructor.php" 2>/dev/null
ls -la "$RUTA/modules/pedidos_tienda/index.php" 2>/dev/null

echo ""
echo "======================================================"
echo "  Listo! Recarga la pagina en el navegador."
echo "  Para futuros archivos nuevos, ejecuta: rbsfix"
echo "======================================================"
