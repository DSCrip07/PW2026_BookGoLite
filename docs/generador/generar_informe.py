# Genera docs/Informe_Proyecto_Parcial2.pdf a partir de docs/datos_informe.json
# Uso:  python docs/generador/generar_informe.py
import html
import json
import os
import subprocess

import matplotlib
from PIL import Image as PILImage
from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_JUSTIFY
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import cm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (BaseDocTemplate, Frame, Image, KeepTogether, NextPageTemplate,
                                PageBreak, PageTemplate, Paragraph, Spacer, Table, TableStyle)

AQUI = os.path.dirname(os.path.abspath(__file__))
DOCS = os.path.abspath(os.path.join(AQUI, ".."))
RAIZ = os.path.abspath(os.path.join(DOCS, ".."))
CAPT = os.path.join(DOCS, "capturas")
EVID = os.path.join(RAIZ, "security", "evidencias")
SALIDA = os.path.join(DOCS, "Informe_Proyecto_Parcial2.pdf")

with open(os.path.join(DOCS, "datos_informe.json"), encoding="utf-8") as f:
    D = json.load(f)

# ---- Fuentes (DejaVu incluida con matplotlib: soporta acentos y símbolos) ----
ft = os.path.join(matplotlib.get_data_path(), "fonts", "ttf")
pdfmetrics.registerFont(TTFont("DV", os.path.join(ft, "DejaVuSans.ttf")))
pdfmetrics.registerFont(TTFont("DV-B", os.path.join(ft, "DejaVuSans-Bold.ttf")))
pdfmetrics.registerFont(TTFont("DV-I", os.path.join(ft, "DejaVuSans-Oblique.ttf")))
pdfmetrics.registerFontFamily("DV", normal="DV", bold="DV-B", italic="DV-I", boldItalic="DV-B")

DORADO = colors.HexColor("#8a6a1f")
OSCURO = colors.HexColor("#2a2118")
ROJO = "#b00020"

S = {
    "base": ParagraphStyle("base", fontName="DV", fontSize=9.6, leading=14, alignment=TA_JUSTIFY, spaceAfter=6),
    "celda": ParagraphStyle("celda", fontName="DV", fontSize=8.6, leading=11.5),
    "celdab": ParagraphStyle("celdab", fontName="DV-B", fontSize=8.6, leading=11.5, textColor=colors.white),
    "h1": ParagraphStyle("h1", fontName="DV-B", fontSize=15, leading=19, textColor=OSCURO, spaceBefore=4, spaceAfter=8),
    "h2": ParagraphStyle("h2", fontName="DV-B", fontSize=11.5, leading=15, textColor=DORADO, spaceBefore=8, spaceAfter=4),
    "cap": ParagraphStyle("cap", fontName="DV-I", fontSize=8.2, leading=10.5, alignment=TA_CENTER,
                          textColor=colors.HexColor("#444444"), spaceAfter=8),
    "portada_t": ParagraphStyle("pt", fontName="DV-B", fontSize=24, leading=30, alignment=TA_CENTER, textColor=OSCURO),
    "portada_s": ParagraphStyle("ps", fontName="DV", fontSize=12.5, leading=18, alignment=TA_CENTER),
    "portada_u": ParagraphStyle("pu", fontName="DV-B", fontSize=12, leading=17, alignment=TA_CENTER, textColor=DORADO),
    "lista": ParagraphStyle("lista", fontName="DV", fontSize=9.6, leading=14, leftIndent=14, bulletIndent=2, spaceAfter=2),
}


def P(txt, est="base"):
    return Paragraph(txt, S[est])


def pendiente(txt):
    return f'<font color="{ROJO}"><b>[PENDIENTE: {txt}]</b></font>'


def lista(items):
    return [Paragraph(t, S["lista"], bulletText="•") for t in items]


def tabla(filas, anchos, cabecera=True):
    datos = []
    for i, fila in enumerate(filas):
        est = "celdab" if (cabecera and i == 0) else "celda"
        datos.append([Paragraph(str(c), S[est]) for c in fila])
    t = Table(datos, colWidths=anchos, repeatRows=1 if cabecera else 0)
    estilo = [("GRID", (0, 0), (-1, -1), 0.4, colors.HexColor("#b9ad94")),
              ("VALIGN", (0, 0), (-1, -1), "TOP"),
              ("LEFTPADDING", (0, 0), (-1, -1), 5), ("RIGHTPADDING", (0, 0), (-1, -1), 5),
              ("TOPPADDING", (0, 0), (-1, -1), 4), ("BOTTOMPADDING", (0, 0), (-1, -1), 4)]
    if cabecera:
        estilo.append(("BACKGROUND", (0, 0), (-1, 0), OSCURO))
        for i in range(1, len(datos)):
            if i % 2 == 0:
                estilo.append(("BACKGROUND", (0, i), (-1, i), colors.HexColor("#f6f1e6")))
    t.setStyle(TableStyle(estilo))
    return t


def figura(ruta, pie, max_w=14.5 * cm, max_h=9.2 * cm):
    if not os.path.isfile(ruta):
        return [P(pendiente(f"falta la imagen {os.path.basename(ruta)}"), "cap")]
    w, h = PILImage.open(ruta).size
    esc = min(max_w / w, max_h / h)
    img = Image(ruta, width=w * esc, height=h * esc)
    img.hAlign = "CENTER"
    return [KeepTogether([img, Spacer(1, 3), P(pie, "cap")])]


def git_historial():
    try:
        out = subprocess.run(
            ["git", "-C", RAIZ, "log", "--reverse", "--date=short", "--format=%h|%an|%ad|%s"],
            capture_output=True, text=True, encoding="utf-8", timeout=20).stdout
        filas = [l.split("|", 3) for l in out.strip().splitlines() if l.count("|") >= 3]
        return filas
    except Exception:
        return []


grupo = D.get("grupo") or ""
grupo_txt = f"Grupo {grupo}" if grupo else None
ref_grupo = grupo_txt or pendiente("número de grupo")
INT = D["integrantes"]

hist = []

# ================= PORTADA =================
hist += [Spacer(1, 1.2 * cm),
         P("UNIVERSIDAD TECNOLÓGICA DE PANAMÁ", "portada_u"),
         P("Facultad de Ingeniería en Sistemas Computacionales<br/>Departamento de Programación de Computadoras", "portada_s"),
         Spacer(1, 1.6 * cm),
         P("PROYECTO PARCIAL 2", "portada_s"),
         Spacer(1, 0.3 * cm),
         P("Book&amp;Go Lite", "portada_t"),
         P("Gestor de citas con reseñas", "portada_s"),
         Spacer(1, 0.4 * cm),
         P("Negocio elegido: <b>Navaja &amp; Estilo</b> (barbería)", "portada_s"),
         Spacer(1, 1.6 * cm),
         P("Asignatura: Desarrollo Web<br/>Profesor: Giovani Sánchez", "portada_s"),
         Spacer(1, 0.9 * cm),
         P(f"<b>{ref_grupo}</b>", "portada_s"),
         Spacer(1, 0.5 * cm),
         P("<br/>".join(f"{i['nombre']}" for i in INT), "portada_s"),
         Spacer(1, 1.2 * cm),
         P(f"Fecha de entrega: {D['fecha_entrega']}", "portada_s"),
         P(f"Repositorio: {D['repo']}", "portada_s"),
         PageBreak()]

# ================= 1. INTEGRANTES =================
hist += [P("1. Integrantes y responsabilidades", "h1"),
         P("El proyecto se desarrolló en equipo de cuatro estudiantes, con un módulo asignado a cada uno según la "
           "distribución sugerida en el enunciado."),
         tabla([["Integrante", "Usuario de GitHub", "Módulo asignado"]] +
               [[i["nombre"], i["github"], i["modulo"]] for i in INT], [5.2 * cm, 4.2 * cm, 7.2 * cm]),
         Spacer(1, 8)]

# ================= 2. NEGOCIO =================
hist += [P("2. Negocio elegido", "h1"),
         P("<b>Navaja &amp; Estilo</b> es una barbería que gestiona sus citas por internet. Los clientes se registran, "
           "exploran los servicios, reservan un horario con cupos disponibles, pueden cancelar su reserva y, una "
           "vez realizada la atención, publicar una reseña. El administrador gestiona categorías, servicios, horarios "
           "y el estado de las reservas."),
         P("<b>Categorías:</b> Cortes, Barba y Tratamientos. <b>Servicios de ejemplo:</b>"),
         tabla([["Servicio", "Categoría", "Precio", "Duración"],
                ["Corte clásico", "Cortes", "$8.00", "30 min"],
                ["Corte degradado (fade)", "Cortes", "$10.00", "45 min"],
                ["Corte infantil", "Cortes", "$6.00", "30 min"],
                ["Perfilado de barba", "Barba", "$6.00", "30 min"],
                ["Afeitado con toalla caliente", "Barba", "$9.00", "40 min"],
                ["Tratamiento capilar", "Tratamientos", "$12.00", "60 min"]],
               [6.6 * cm, 3.6 * cm, 3.2 * cm, 3.2 * cm]),
         Spacer(1, 6),
         P("<b>Tecnologías:</b> PHP 8.2 (PDO), MySQL/MariaDB, HTML5, CSS3 y JavaScript, sobre XAMPP (Apache). "
           "<b>Plantilla o biblioteca visual:</b> ninguna; el diseño (paleta carbón y dorado, tipografía y componentes) "
           "es CSS propio, sin Bootstrap ni otras bibliotecas externas.")]

# ================= 3. ER =================
hist += [NextPageTemplate("horizontal"), PageBreak(), P("3. Diagrama entidad-relación", "h1"),
         P("El esquema tiene siete tablas relacionadas, todas con clave primaria: <b>roles, usuarios, categorias, "
           "servicios, horarios, reservas y resenas</b>.")]
hist += figura(os.path.join(DOCS, "diagrama_er.png"), "Figura: diagrama entidad-relación de bookgo_barberia",
               max_w=26.5 * cm, max_h=13.2 * cm)
hist += [NextPageTemplate("vertical"), PageBreak()]
hist += [P("Cómo las relaciones impiden datos inconsistentes", "h2")] + lista([
    "<b>Horario del servicio correcto:</b> la reserva tiene una clave foránea compuesta (horario_id, servicio_id) hacia "
    "horarios; la base de datos rechaza reservar un horario que no pertenece al servicio indicado.",
    "<b>Sin doble reserva activa:</b> una columna generada (horario_activo) solo toma valor mientras la reserva está "
    "PENDIENTE o CONFIRMADA; con un índice único (cliente_id, horario_activo) un cliente no puede tener dos reservas "
    "activas en el mismo horario.",
    "<b>Cupos válidos:</b> cupos_disponibles es sin signo (nunca negativo) y una restricción CHECK impide que supere "
    "los cupos totales.",
    "<b>Datos válidos:</b> CHECK para precio mayor o igual a 0, duración mayor que 0, hora final posterior a la inicial "
    "y calificación entre 1 y 5.",
    "<b>Unicidad:</b> el correo del usuario es único y reserva_id es único en resenas (una reseña por reserva).",
    "<b>Integridad referencial:</b> todas las relaciones usan claves foráneas; los registros con dependencias no se "
    "eliminan físicamente, se desactivan."])

# ================= 4. MÓDULOS Y CAPTURAS =================
MODULOS = [
    ("4.1 Autenticación y sesiones",
     "Registro público (crea únicamente usuarios CLIENTE), inicio y cierre de sesión, validación de credenciales y "
     "estado, protección CSRF, política de contraseñas (8 o más caracteres con mayúscula, minúscula, número y carácter "
     "especial) y bloqueo temporal tras cinco intentos fallidos. El ADMIN se crea con un script de consola.",
     [("01_login.png", "Inicio de sesión (mensaje tras cerrar sesión)"),
      ("02_registro.png", "Formulario de registro con la regla de contraseña"),
      ("03_registro_contrasena.png", "Validación de la contraseña en el formulario")]),
    ("4.2 Catálogo y administración",
     "Catálogo con búsqueda por nombre y filtro por categoría, detalle del servicio y panel del ADMIN con CRUD de "
     "categorías y servicios (crear, editar, desactivar y eliminar solo si no tiene dependencias).",
     [("04_catalogo_filtro.png", "Catálogo con búsqueda y filtro por categoría"),
      ("05_detalle_servicio.png", "Detalle del servicio: horarios con cupos y reseñas"),
      ("09_admin_panel.png", "Panel de administración"),
      ("10_admin_categorias.png", "Mantenimiento de categorías"),
      ("11_admin_servicios.png", "Mantenimiento de servicios")]),
    ("4.3 Horarios y reservas",
     "CRUD de horarios con validaciones (no en el pasado, hora final posterior a la inicial), reservas con control de "
     "cupos en transacción, cancelaciones y cambio de estados por el ADMIN (PENDIENTE, CONFIRMADA, REALIZADA, "
     "CANCELADA).",
     [("12_admin_horarios.png", "Mantenimiento de horarios con cupos totales y disponibles"),
      ("06_reserva_creada.png", "Reserva creada en estado PENDIENTE"),
      ("07_reserva_cancelada.png", "Reserva cancelada: se devuelve el cupo"),
      ("13_admin_reservas.png", "Gestión de reservas por el ADMIN")]),
    ("4.4 Reseñas, interfaz y diseño adaptable",
     "Un cliente puede publicar una sola reseña (1 a 5 y comentario) por cada reserva propia REALIZADA; las reseñas se "
     "muestran en el detalle del servicio. La interfaz se adapta a computadora y teléfono.",
     [(("evid", "02_xss_resena.png"), "Reseña publicada y mostrada en el detalle del servicio"),
      ("14_movil_admin_reservas.png", "Vista en pantalla de teléfono")]),
    ("4.5 Base de datos",
     "Esquema de siete tablas importable desde database/schema.sql, con datos de prueba.",
     [("15_phpmyadmin_tablas.png", "Las siete tablas en phpMyAdmin"),
      ("16_phpmyadmin_relaciones.png", "Relaciones entre tablas (vista Diseñador)")]),
]
hist += [PageBreak(), P("4. Módulos y capturas de pantalla", "h1")]
for titulo, desc, imgs in MODULOS:
    hist += [P(titulo, "h2"), P(desc)]
    for arch, pie in imgs:
        ruta = os.path.join(EVID, arch[1]) if isinstance(arch, tuple) else os.path.join(CAPT, arch)
        hist += figura(ruta, pie, max_w=14 * cm, max_h=8.3 * cm)

# ================= 5. RESERVAS =================
hist += [PageBreak(), P("5. Reglas de negocio de las reservas", "h1")] + lista([
    "El cliente elige un servicio y un horario futuro con cupos disponibles. Al reservar, la reserva queda "
    "<b>PENDIENTE</b> y se descuenta un cupo.",
    "El cupo se descuenta y la reserva se registra en <b>una sola transacción</b> (SELECT ... FOR UPDATE y "
    "UPDATE ... WHERE cupos_disponibles &gt; 0), de modo que dos clientes simultáneos no pueden superar el límite. "
    "No depende de deshabilitar un botón en HTML.",
    "Un mismo cliente no puede mantener dos reservas activas para el mismo horario.",
    "El ADMIN pasa PENDIENTE a CONFIRMADA y luego a REALIZADA (no puede saltarse pasos) y puede cancelar.",
    "El cliente solo cancela reservas propias que no estén REALIZADAS y cuya cita no haya comenzado.",
    "Al cancelar una reserva PENDIENTE o CONFIRMADA se devuelve exactamente un cupo; cancelar de nuevo no devuelve "
    "cupos adicionales. Una reserva REALIZADA no se cancela ni devuelve cupos.",
    "Los cupos nunca son negativos ni superiores al total."])

# ================= 6. SEGURIDAD =================
PRUEBAS = [
    ("Prueba 1 – SQL Injection (búsqueda y login)",
     "Verificar que una entrada maliciosa no altera la consulta ni evita la autenticación.",
     "La búsqueda con <i>' OR '1'='1</i> devuelve 0 servicios (no todos) y el login con ese texto como contraseña no "
     "inicia sesión.",
     "PDO con consultas preparadas y parámetros enlazados en toda la aplicación.",
     [("01a_sqli_busqueda.png", "Búsqueda con intento de inyección SQL"),
      ("01b_sqli_login.png", "Login con intento de inyección SQL")]),
    ("Prueba 2 – XSS en comentario de reseña",
     "Verificar que un script ingresado se muestra como texto y no se ejecuta.",
     "El comentario &lt;script&gt;alert('XSS')&lt;/script&gt; se muestra literalmente y no aparece ninguna alerta.",
     "Salida codificada con htmlspecialchars (ENT_QUOTES, UTF-8), validación de entrada y Content-Security-Policy.",
     [("02_xss_resena.png", "Script mostrado como texto en la reseña")]),
    ("Prueba 3 – Acceso directo de un CLIENTE a una página ADMIN",
     "Comprobar que el rol se valida en PHP y no solo en el menú.",
     "Un cliente que escribe la URL de una página de administración recibe «Acceso denegado» (HTTP 403); sin sesión "
     "se redirige al login.",
     "Función requiere_rol('ADMIN') al inicio de cada página de administración.",
     [("03_acceso_admin_denegado.png", "Acceso denegado a un cliente")]),
    ("Prueba 4 – Acceso a la reserva de otro cliente",
     "Comprobar que cada cliente solo ve o modifica lo que le pertenece.",
     "Al abrir la reserva de otro cliente se muestra «Solo puede reseñar sus propias reservas»; un intento de "
     "cancelar una reserva ajena responde «Reserva no encontrada» y no cambia el estado.",
     "Consultas filtradas siempre por el id del usuario de la sesión.",
     [("04_reserva_ajena.png", "Intento de acceder a una reserva ajena")]),
    ("Prueba 5 – Formulario sin token CSRF válido",
     "Verificar que una operación sin token (o con token falso) es rechazada.",
     "Aparece «Solicitud rechazada: token de seguridad inválido» (HTTP 403) y la reserva no cambia de estado.",
     "Token aleatorio por sesión, verificado con hash_equals en cada POST que modifica datos.",
     [("05_csrf_rechazado.png", "Operación rechazada por token CSRF inválido")]),
    ("Prueba 6 – Cinco intentos de login fallidos",
     "Comprobar el bloqueo temporal sin revelar si el correo existe.",
     "Tras cinco intentos se bloquea el acceso con ese correo durante 5 minutos, con cuenta regresiva; incluso la "
     "contraseña correcta es rechazada. Otras cuentas pueden iniciar sesión.",
     "Contador de intentos y bloqueo por usuario en la base de datos y por sesión, con el mismo mensaje para correos "
     "existentes e inexistentes.",
     [("06_bloqueo_login.png", "Bloqueo temporal con cuenta regresiva")]),
    ("Prueba 7 – Inspección de cabeceras HTTP",
     "Verificar que el servidor envía las cabeceras de seguridad configuradas.",
     "Se observan Content-Security-Policy, X-Content-Type-Options: nosniff y X-Frame-Options: DENY.",
     "Cabeceras enviadas desde includes/bootstrap.php en todas las páginas.",
     [("07a_cabecera_csp.png", "Cabecera Content-Security-Policy"),
      ("07b_cabeceras_nosniff_frame.png", "Cabeceras X-Content-Type-Options y X-Frame-Options")]),
]
hist += [PageBreak(), P("6. Controles de seguridad y pruebas", "h1"),
         P("Todas las pruebas se realizaron únicamente en la aplicación de desarrollo (localhost). El detalle de cada "
           "una está en <b>security/SECURITY.md</b> y las capturas en <b>security/evidencias/</b>."),
         tabla([["Control", "Implementación"],
                ["Contraseñas", "password_hash() / password_verify(); mínimo 8 caracteres con mayúscula, minúscula, número y carácter especial"],
                ["Sesiones", "session_regenerate_id al iniciar sesión; sesión y cookie destruidas al salir; cookie HttpOnly y SameSite=Lax (Secure con HTTPS)"],
                ["Intentos de login", "Bloqueo temporal de 5 minutos tras 5 intentos fallidos, sin revelar si el correo existe"],
                ["SQL Injection", "PDO y consultas preparadas con parámetros"],
                ["XSS", "htmlspecialchars con ENT_QUOTES y UTF-8; validación de entradas"],
                ["CSRF", "Token por sesión verificado en cada operación que cambia datos"],
                ["Autorización", "Rol y propiedad de cada recurso verificados en el servidor"],
                ["Cabeceras HTTP", "Content-Security-Policy, X-Content-Type-Options, X-Frame-Options, Referrer-Policy"],
                ["Errores", "Mensajes genéricos al usuario; el detalle solo se registra en el servidor"]],
               [3.6 * cm, 13 * cm])]
for titulo, obj, res, ctrl, imgs in PRUEBAS:
    bloque = [P(titulo, "h2"),
              P(f"<b>Objetivo:</b> {obj}<br/><b>Resultado observado:</b> {res}<br/><b>Control aplicado:</b> {ctrl}")]
    hist += bloque
    for arch, pie in imgs:
        hist += figura(os.path.join(EVID, arch), pie, max_w=13 * cm, max_h=6.8 * cm)

# ================= 7. DISTRIBUCIÓN + HISTORIAL =================
hist += [PageBreak(), P("7. Distribución del trabajo y GitHub", "h1"),
         P(f"Repositorio compartido: <b>{D['repo']}</b>. Los cuatro integrantes fueron incorporados como "
           "colaboradores y subieron su módulo con commits desde su propia cuenta."),
         tabla([["Módulo", "Responsable", "Archivos principales"],
                ["Autenticación y sesiones", INT[0]["nombre"], "includes/bootstrap.php, header.php, footer.php, password_policy.php; public/login.php, registro.php, logout.php; database/create_admin.php"],
                ["Catálogo y administración", INT[1]["nombre"], "database/schema.sql; public/index.php, catalogo.php, servicio.php; public/admin/index.php, categorias.php, servicios.php"],
                ["Horarios y reservas", INT[2]["nombre"], "includes/reservas.php; public/reservar.php, mis_reservas.php; public/admin/horarios.php, reservas.php"],
                ["Reseñas, interfaz y pruebas", INT[3]["nombre"], "public/resenar.php; public/css/style.css; security/SECURITY.md y evidencias"]],
               [4.3 * cm, 3.8 * cm, 8.5 * cm]),
         P("Historial de contribuciones", "h2")]
filas = git_historial()
if filas:
    hist.append(tabla([["Commit", "Autor", "Fecha", "Descripción"]] + [[a, b, c, d] for a, b, c, d in filas],
                      [1.8 * cm, 3.6 * cm, 2.4 * cm, 8.8 * cm]))
else:
    hist.append(P(pendiente("historial de commits")))
hist += figura(os.path.join(CAPT, "17_github_commits.png"), "Historial de commits en GitHub", max_w=15 * cm, max_h=8 * cm)

# ================= 8. IA =================
TEXTO_IA = D.get("texto_ia")
hist += [PageBreak(), P("8. Uso de inteligencia artificial", "h1")]
if TEXTO_IA:
    hist.append(P(TEXTO_IA))
else:
    hist += [
        P("En cumplimiento de la sección 11 del enunciado, el grupo declara el uso de herramientas de IA:"),
        P("<b>Herramienta:</b> Claude Code (Anthropic), asistente de IA usado desde la aplicación de escritorio de Claude."),
        P("<b>Para qué se usó:</b>"),
    ] + lista([
        "Generación del código base de la aplicación (PHP, SQL, CSS y JavaScript) a partir del enunciado.",
        "Diseño del esquema de siete tablas y de las restricciones de integridad.",
        "Implementación de los controles de seguridad (contraseñas, sesiones, CSRF, XSS, SQL Injection, bloqueo de intentos y cabeceras).",
        "Implementación de la política de contraseñas con carácter especial y del bloqueo por intentos, solicitados por el profesor.",
        "Diagnóstico y corrección de errores encontrados al probar: acentos corruptos al importar el SQL, hoja de estilos que no cargaba en XAMPP por la ruta base, desajuste de zona horaria en el bloqueo y código HTTP 419 que Apache convertía en 500.",
        "Redacción de security/SECURITY.md, del diagrama entidad-relación, de este informe y de los borradores de las reflexiones individuales, que cada integrante debe revisar y ajustar."]) + [
        P("<b>Verificación:</b> la IA ejecutó pruebas por consola de las reglas de negocio (cupos, estados, "
          "duplicados, propiedad de recursos) y de los controles de seguridad; el grupo reprodujo las pruebas de "
          "seguridad en el navegador y tomó las capturas que se incluyen como evidencia."),
        P("<b>Aportes del grupo:</b> elección del negocio, revisión y prueba de la aplicación en su propio entorno "
          "(XAMPP), creación del repositorio y de los commits desde las cuentas de cada integrante, captura de "
          "evidencias y revisión de las reflexiones individuales."),
        P("Cada integrante se compromete a poder explicar el código de su módulo y las consultas, relaciones, "
          "permisos y validaciones que el profesor solicite durante la sustentación.")]

# ================= 9. REFLEXIONES =================
hist += [PageBreak(), P("9. Reflexión individual", "h1")]
for i in INT:
    r = (i.get("reflexion") or "").strip()
    hist += [P(f"{i['nombre']} – {i['modulo']}", "h2"),
             P(html.escape(r) if r else pendiente(f"reflexión de {i['nombre']}"))]


def _pie(canvas, doc, ancho):
    if doc.page == 1:           # la portada no lleva pie
        return
    canvas.saveState()
    canvas.setFont("DV", 8)
    canvas.setFillColor(colors.HexColor("#666666"))
    g = f"Grupo {grupo}" if grupo else "Grupo [pendiente]"
    canvas.drawString(2 * cm, 1.2 * cm, f"Book&Go Lite – Navaja & Estilo · {g}")
    canvas.drawRightString(ancho - 2 * cm, 1.2 * cm, f"Página {doc.page}")
    canvas.restoreState()


PW, PH = A4
LW, LH = landscape(A4)
doc = BaseDocTemplate(SALIDA, pagesize=A4, title="Informe Proyecto Parcial 2 – Book&Go Lite",
                      author="Grupo " + grupo)
doc.addPageTemplates([
    PageTemplate(id="vertical", pagesize=A4,
                 frames=[Frame(2 * cm, 1.9 * cm, PW - 4 * cm, PH - 3.7 * cm, id="v")],
                 onPage=lambda c, d: _pie(c, d, PW)),
    PageTemplate(id="horizontal", pagesize=landscape(A4),
                 frames=[Frame(1.5 * cm, 1.8 * cm, LW - 3 * cm, LH - 3.4 * cm, id="h")],
                 onPage=lambda c, d: _pie(c, d, LW)),
])
doc.build(hist)
print("OK", SALIDA)
