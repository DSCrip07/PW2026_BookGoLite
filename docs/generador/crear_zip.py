# Crea PW2026_Grupo<N>_BookGoLite.zip con el código fuente completo (sin .git ni configuración local).
# Uso:  python docs/generador/crear_zip.py [carpeta_de_salida]
# El número de grupo se toma de docs/datos_informe.json ("X" si aún no está definido).
import json
import os
import shutil
import sys
import zipfile

AQUI = os.path.dirname(os.path.abspath(__file__))
RAIZ = os.path.abspath(os.path.join(AQUI, "..", ".."))
CARPETA = os.path.basename(RAIZ)
SALIDA_DIR = os.path.abspath(sys.argv[1]) if len(sys.argv) > 1 else os.path.join(RAIZ, "..")

with open(os.path.join(RAIZ, "docs", "datos_informe.json"), encoding="utf-8") as f:
    grupo = (json.load(f).get("grupo") or "X").strip() or "X"

NOMBRE = f"PW2026_Grupo{grupo}_BookGoLite.zip"
EXCLUIR_DIRS = {".git", "__pycache__", "node_modules"}
EXCLUIR_ARCH = {"config.local.php"}

os.makedirs(SALIDA_DIR, exist_ok=True)
destino = os.path.join(SALIDA_DIR, NOMBRE)
n = 0
with zipfile.ZipFile(destino, "w", zipfile.ZIP_DEFLATED) as z:
    for base, dirs, files in os.walk(RAIZ):
        dirs[:] = [d for d in dirs if d not in EXCLUIR_DIRS]
        for f in files:
            if f in EXCLUIR_ARCH or f.endswith(".zip") or f == ".gitkeep":
                continue
            ruta = os.path.join(base, f)
            arc = os.path.join(CARPETA, os.path.relpath(ruta, RAIZ)).replace("\\", "/")
            z.write(ruta, arc)
            n += 1

# Copia del informe PDF junto al ZIP (se entrega aparte)
pdf = os.path.join(RAIZ, "docs", "Informe_Proyecto_Parcial2.pdf")
if os.path.isfile(pdf):
    shutil.copy2(pdf, os.path.join(SALIDA_DIR, "Informe_Proyecto_Parcial2.pdf"))

print(f"OK {destino}  ({n} archivos, {os.path.getsize(destino) // 1024} KB)")
