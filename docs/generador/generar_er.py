# Genera docs/diagrama_er.png (diagrama entidad-relación de Book&Go Lite), en formato horizontal.
# Uso:  python docs/generador/generar_er.py
import os
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
from matplotlib.patches import Rectangle

AQUI = os.path.dirname(os.path.abspath(__file__))
SALIDA = os.path.join(AQUI, "..", "diagrama_er.png")

HDR, ROW_H = 0.68, 0.46
COL_HDR, COL_BORDE, COL_PK, COL_FK = "#3a2c1c", "#6b5a3e", "#8a5a00", "#1f4e8c"

# nombre: (x, y, ancho, [(marca, campo, nota)])
T = {
    "roles": (0.5, 6.8, 3.8, [("PK", "id", "TINYINT"), ("", "nombre", "UNIQUE")]),
    "usuarios": (0.5, 0.9, 4.3, [
        ("PK", "id", "INT"), ("FK", "rol_id", "TINYINT"), ("", "nombre", "VARCHAR"),
        ("", "apellido", "VARCHAR"), ("", "correo", "UNIQUE"),
        ("", "password_hash", "VARCHAR"), ("", "estado", "ENUM"),
        ("", "intentos_fallidos", "TINYINT"), ("", "bloqueado_hasta", "DATETIME"),
        ("", "fecha_registro", "DATETIME")]),
    "horarios": (5.9, 6.8, 4.7, [
        ("PK", "id", "INT"), ("FK", "servicio_id", "INT"), ("", "fecha", "DATE"),
        ("", "hora_inicio", "TIME"), ("", "hora_fin", "CHECK > inicio"),
        ("", "cupos_totales", "SMALLINT"), ("", "cupos_disponibles", "<= totales"),
        ("", "estado", "ENUM")]),
    "reservas": (5.9, 0.9, 4.7, [
        ("PK", "id", "INT"), ("FK", "cliente_id", "INT"), ("FK", "servicio_id", "INT"),
        ("FK", "horario_id", "INT"), ("", "estado", "ENUM (4)"),
        ("", "fecha_registro", "DATETIME"), ("", "fecha_actualizacion", "DATETIME"),
        ("", "horario_activo", "UNIQUE c/ cliente")]),
    "servicios": (11.7, 6.8, 3.8, [
        ("PK", "id", "INT"), ("FK", "categoria_id", "INT"), ("", "nombre", "VARCHAR"),
        ("", "descripcion", "TEXT"), ("", "precio", "CHECK >= 0"),
        ("", "duracion_min", "CHECK > 0"), ("", "estado", "ENUM")]),
    "resenas": (11.7, 0.9, 3.8, [
        ("PK", "id", "INT"), ("FK", "reserva_id", "UNIQUE"), ("FK", "cliente_id", "INT"),
        ("", "calificacion", "CHECK 1-5"), ("", "comentario", "VARCHAR"),
        ("", "fecha", "DATETIME")]),
    "categorias": (17.1, 6.8, 3.8, [
        ("PK", "id", "INT"), ("", "nombre", "UNIQUE"),
        ("", "descripcion", "VARCHAR"), ("", "estado", "ENUM")]),
}


def alto(n):
    return HDR + ROW_H * len(T[n][3])


fig, ax = plt.subplots(figsize=(21.6, 13.6), dpi=110)
ax.set_xlim(0, 21.6)
ax.set_ylim(0, 13.6)
ax.axis("off")

for nombre, (x, y, w, campos) in T.items():
    h = alto(nombre)
    ax.add_patch(Rectangle((x, y), w, h, fc="white", ec=COL_BORDE, lw=1.8, zorder=3))
    ax.add_patch(Rectangle((x, y + h - HDR), w, HDR, fc=COL_HDR, ec=COL_BORDE, lw=1.8, zorder=4))
    ax.text(x + w / 2, y + h - HDR / 2, nombre, color="#f3dfa6", ha="center", va="center",
            fontsize=16, fontweight="bold", zorder=5)
    for i, (marca, campo, nota) in enumerate(campos):
        yy = y + h - HDR - ROW_H * (i + 0.5)
        if i % 2 == 1:
            ax.add_patch(Rectangle((x, yy - ROW_H / 2), w, ROW_H, fc="#f6f1e6", ec="none", zorder=3.5))
        col = COL_PK if marca == "PK" else COL_FK if marca == "FK" else "#222222"
        ax.text(x + 0.12, yy, marca, color=col, fontsize=11, fontweight="bold", va="center", zorder=5)
        ax.text(x + 0.66, yy, campo, color=col, fontsize=13.5, va="center", zorder=5,
                fontweight="bold" if marca == "PK" else "normal")
        ax.text(x + w - 0.1, yy, nota, color="#666666", fontsize=10.5, va="center", ha="right", zorder=5)


def linea(pts, color="#444444"):
    xs, ys = zip(*pts)
    ax.plot(xs, ys, color=color, lw=2.0, zorder=2, solid_capstyle="round")


def etiqueta(x, y, txt):
    ax.text(x, y, txt, fontsize=14, fontweight="bold", color="#b00020", ha="center", va="center",
            zorder=6, bbox=dict(boxstyle="round,pad=0.1", fc="white", ec="none"))


def der(n):
    return T[n][0] + T[n][2]


def cx(n):
    return T[n][0] + T[n][2] / 2


def top(n):
    return T[n][1] + alto(n)


# roles 1 — N usuarios
linea([(cx("roles"), T["roles"][1]), (cx("roles"), top("usuarios"))])
etiqueta(cx("roles") + 0.3, T["roles"][1] - 0.3, "1")
etiqueta(cx("roles") + 0.3, top("usuarios") + 0.3, "N")

# usuarios 1 — N reservas
ym = 2.6
linea([(der("usuarios"), ym), (T["reservas"][0], ym)])
etiqueta(der("usuarios") + 0.3, ym + 0.32, "1")
etiqueta(T["reservas"][0] - 0.3, ym + 0.32, "N")

# horarios 1 — N reservas (FK compuesta)
xh = T["horarios"][0] + 1.0
linea([(xh, T["horarios"][1]), (xh, top("reservas"))])
etiqueta(xh + 0.3, T["horarios"][1] - 0.3, "1")
etiqueta(xh + 0.3, top("reservas") + 0.3, "N")
ax.text(xh + 0.6, (T["horarios"][1] + top("reservas")) / 2,
        "FK compuesta (horario_id, servicio_id):\nel horario pertenece al servicio reservado",
        fontsize=10.5, color="#1f4e8c", va="center", zorder=6,
        bbox=dict(boxstyle="round,pad=0.25", fc="#eef4fb", ec="#1f4e8c", lw=0.9))

# servicios 1 — N horarios
yh = 8.6
linea([(T["servicios"][0], yh), (der("horarios"), yh)])
etiqueta(T["servicios"][0] - 0.3, yh + 0.32, "1")
etiqueta(der("horarios") + 0.3, yh + 0.32, "N")

# categorias 1 — N servicios
yc = 8.2
linea([(T["categorias"][0], yc), (der("servicios"), yc)])
etiqueta(T["categorias"][0] - 0.3, yc + 0.32, "1")
etiqueta(der("servicios") + 0.3, yc + 0.32, "N")

# servicios 1 — N reservas (codo)
xs = T["servicios"][0] + 0.9
yr = 4.85
linea([(xs, T["servicios"][1]), (xs, yr), (der("reservas"), yr)])
etiqueta(xs + 0.3, T["servicios"][1] - 0.3, "1")
etiqueta(der("reservas") + 0.3, yr + 0.32, "N")

# reservas 1 — 1 resenas
y2 = 2.0
linea([(der("reservas"), y2), (T["resenas"][0], y2)])
etiqueta(der("reservas") + 0.3, y2 + 0.32, "1")
etiqueta(T["resenas"][0] - 0.3, y2 + 0.32, "1")

# usuarios 1 — N resenas (por debajo)
yb = 0.35
xr = T["resenas"][0] + 1.9
linea([(cx("usuarios"), T["usuarios"][1]), (cx("usuarios"), yb), (xr, yb), (xr, T["resenas"][1])])
etiqueta(cx("usuarios") + 0.3, T["usuarios"][1] - 0.25, "1")
etiqueta(xr + 0.3, T["resenas"][1] - 0.25, "N")

ax.text(0.5, 13.0, "Book&Go Lite · Navaja & Estilo", fontsize=20, fontweight="bold", color="#3a2c1c")
ax.text(0.5, 12.4, "Diagrama entidad-relación · bookgo_barberia", fontsize=14, color="#3a2c1c")
ax.text(0.5, 11.9, "PK = clave primaria  ·  FK = clave foránea  ·  1 / N = cardinalidad",
        fontsize=11, color="#555555")

fig.savefig(SALIDA, bbox_inches="tight", facecolor="white")
print("OK", os.path.abspath(SALIDA))
