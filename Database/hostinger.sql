-- ════════════════════════════════════════════════════════════════════════
--  EL CUACHE — Instalación en Hostinger (phpMyAdmin)
--  1. Crea la base en hPanel → Bases de datos MySQL.
--  2. Ábrela en phpMyAdmin, pestaña "Importar", y sube ESTE archivo.
--  Es schema.sql + seed.sql sin CREATE DATABASE/USE (en Hostinger no se permite).
--  Generado a partir de esos dos archivos: si cambian, vuelve a generarlo.
-- ════════════════════════════════════════════════════════════════════════

-- ════════════════════════════════════════════════════════════════════════
--  EL CUACHE · Café y Restaurante — Esquema MySQL / MariaDB
--  Aplicar:  mysql -u root < Database/schema.sql   (luego seed.sql)
--
--  Cambios de modelo respecto a El Jardín:
--   · cuentas  = la cuenta de una mesa (lo que se cobra).
--   · comandas = cada envío a cocina (lo que ve el KDS). Antes un pedido
--                mezclaba platillos ya servidos con los recién agregados.
--   · el estado de la mesa ya no se guarda: se deriva de cuentas/llamados,
--     así nunca queda "Ocupada" por error.
--   · mesas.qr_token: el QR identifica la mesa con un token secreto (antes
--     bastaba cambiar ?mesa=Mesa 7 en la URL para pedir a otra mesa).
--   · empleados = personal + acceso al sistema en una sola ficha.
-- ════════════════════════════════════════════════════════════════════════


SET FOREIGN_KEY_CHECKS = 0;

-- ── Configuración general (clave/valor) ─────────────────────────────────
CREATE TABLE IF NOT EXISTS configuracion (
    clave  VARCHAR(60)  PRIMARY KEY,
    valor  VARCHAR(500) NOT NULL
) ENGINE=InnoDB;

-- ── Personal (ficha + acceso al sistema) ─────────────────────────────────
CREATE TABLE IF NOT EXISTS empleados (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    nombre                VARCHAR(80)  NOT NULL,
    apellido              VARCHAR(80)  NOT NULL DEFAULT '',
    foto                  VARCHAR(255) NULL,
    documento_identidad   VARCHAR(30)  NULL,
    fecha_nacimiento      DATE         NULL,
    telefono              VARCHAR(30)  NULL,
    email                 VARCHAR(150) NULL,
    direccion             VARCHAR(255) NULL,
    emergencia_nombre     VARCHAR(120) NULL,
    emergencia_parentesco VARCHAR(40)  NULL,
    emergencia_telefono   VARCHAR(30)  NULL,
    puesto                ENUM('Mesero','Cocinero','Bartender','Cajero','Ayudante','Administrador') NOT NULL DEFAULT 'Mesero',
    fecha_ingreso         DATE         NULL,
    tipo_contrato         ENUM('Indefinido','Temporal','Por horas','Prueba') NOT NULL DEFAULT 'Indefinido',
    tipo_pago             ENUM('Mensual','Por hora') NOT NULL DEFAULT 'Mensual',
    salario               DECIMAL(10,2) NOT NULL DEFAULT 0,
    tarifa_hora           DECIMAL(10,2) NOT NULL DEFAULT 0,
    banco                 VARCHAR(60)  NULL,
    cuenta_bancaria       VARCHAR(40)  NULL,
    talla_camisa          VARCHAR(10)  NULL,
    talla_pantalon        VARCHAR(10)  NULL,
    talla_calzado         VARCHAR(10)  NULL,
    notas_medicas         TEXT         NULL,
    estado                ENUM('activo','vacaciones','suspendido','baja') NOT NULL DEFAULT 'activo',
    fecha_baja            DATE         NULL,
    motivo_baja           VARCHAR(255) NULL,
    dias_vacaciones       INT          NOT NULL DEFAULT 15,
    -- Acceso al sistema
    acceso_activo         TINYINT(1)   NOT NULL DEFAULT 0,
    usuario               VARCHAR(60)  NULL UNIQUE,
    password              VARCHAR(255) NULL,
    rol                   ENUM('Admin','Mesero','Cocinero') NULL,
    pin_hash              CHAR(64)     NULL UNIQUE,   -- HMAC-SHA256 del PIN (búsqueda directa, sin exponer el PIN)
    ultimo_acceso         DATETIME     NULL,
    creado_en             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en        DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_emp_estado (estado),
    INDEX idx_emp_puesto (puesto)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS empleado_documentos (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    empleado_id  INT NOT NULL,
    tipo         ENUM('Contrato','Identificación','Certificado de salud','Otro') NOT NULL DEFAULT 'Otro',
    nombre       VARCHAR(150) NOT NULL,
    archivo      VARCHAR(255) NOT NULL,          -- ruta privada en Backend/storage
    mime         VARCHAR(80)  NULL,
    vence_en     DATE         NULL,
    subido_por   INT          NULL,
    creado_en    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
    INDEX idx_doc_vence (vence_en)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_intentos (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    ip        VARCHAR(45)  NOT NULL,
    usuario   VARCHAR(150) NULL,
    tipo      ENUM('login','pin') NOT NULL DEFAULT 'login',
    exito     TINYINT(1)   NOT NULL DEFAULT 0,
    creado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_ip (ip, tipo, creado_en)
) ENGINE=InnoDB;

-- ── Carta ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS categorias (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    nombre  VARCHAR(80)  NOT NULL,
    icono   VARCHAR(40)  NOT NULL DEFAULT 'ForkKnife',   -- nombre de ícono Phosphor
    orden   INT          NOT NULL DEFAULT 0,
    activa  TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS productos (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id     INT NULL,
    nombre           VARCHAR(150) NOT NULL,
    descripcion      TEXT NULL,
    precio           DECIMAL(10,2) NOT NULL,
    costo            DECIMAL(10,2) NOT NULL DEFAULT 0,
    imagen           VARCHAR(500) NULL,
    imagen_posicion  VARCHAR(20)  NOT NULL DEFAULT '50% 50%',   -- enfoque (object-position)
    disponible       TINYINT(1)   NOT NULL DEFAULT 1,
    agotado_motivo   ENUM('manual','stock') NULL,
    agotado_en       DATETIME     NULL,
    controla_stock   TINYINT(1)   NOT NULL DEFAULT 0,
    stock            INT          NOT NULL DEFAULT 0,
    stock_minimo     INT          NOT NULL DEFAULT 5,
    destacado        TINYINT(1)   NOT NULL DEFAULT 0,
    activo           TINYINT(1)   NOT NULL DEFAULT 1,           -- borrado lógico (el historial conserva el nombre)
    creado_en        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en   DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
    INDEX idx_prod_disp (disponible, activo)
) ENGINE=InnoDB;

-- ── Salón ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS mesas (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    numero     INT          NOT NULL UNIQUE,
    nombre     VARCHAR(40)  NULL,                -- ej. "Terraza 1" (opcional)
    capacidad  INT          NOT NULL DEFAULT 4,
    zona       VARCHAR(40)  NOT NULL DEFAULT 'Salón',
    forma      ENUM('redonda','cuadrada','larga') NOT NULL DEFAULT 'cuadrada',
    pos_x      DECIMAL(5,2) NOT NULL DEFAULT 10,  -- posición en el plano (% del ancho)
    pos_y      DECIMAL(5,2) NOT NULL DEFAULT 10,  -- posición en el plano (% del alto)
    qr_token   CHAR(24)     NOT NULL UNIQUE,
    activa     TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ── Caja ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS turnos_caja (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    abierto_por           INT NOT NULL,
    cerrado_por           INT NULL,
    monto_inicial         DECIMAL(10,2) NOT NULL DEFAULT 0,
    efectivo_esperado     DECIMAL(10,2) NULL,
    efectivo_contado      DECIMAL(10,2) NULL,
    diferencia            DECIMAL(10,2) NULL,
    resumen               LONGTEXT      NULL,     -- JSON con el desglose al cierre
    notas_apertura        VARCHAR(255)  NULL,
    notas_cierre          VARCHAR(255)  NULL,
    estado                ENUM('abierto','cerrado') NOT NULL DEFAULT 'abierto',
    abierto_en            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cerrado_en            DATETIME NULL,
    FOREIGN KEY (abierto_por) REFERENCES empleados(id),
    FOREIGN KEY (cerrado_por) REFERENCES empleados(id),
    INDEX idx_turno_estado (estado)
) ENGINE=InnoDB;

-- ── Cuentas, comandas, pagos ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cuentas (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    mesa_id             INT NULL,
    mesero_id           INT NULL,
    turno_caja_id       INT NULL,
    personas            INT NOT NULL DEFAULT 1,
    estado              ENUM('abierta','pagada','cancelada') NOT NULL DEFAULT 'abierta',
    subtotal            DECIMAL(10,2) NOT NULL DEFAULT 0,   -- suma de items activos
    descuento           DECIMAL(10,2) NOT NULL DEFAULT 0,
    descuento_motivo    VARCHAR(255)  NULL,
    pagado              DECIMAL(10,2) NOT NULL DEFAULT 0,   -- suma de pagos vigentes (sin propina)
    propina             DECIMAL(10,2) NOT NULL DEFAULT 0,
    motivo_cancelacion  VARCHAR(255)  NULL,
    abierta_en          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cerrada_en          DATETIME NULL,
    FOREIGN KEY (mesa_id)   REFERENCES mesas(id) ON DELETE SET NULL,
    FOREIGN KEY (mesero_id) REFERENCES empleados(id) ON DELETE SET NULL,
    INDEX idx_cuenta_estado (estado, abierta_en),
    INDEX idx_cuenta_mesa (mesa_id, estado)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS comandas (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    cuenta_id     INT NOT NULL,
    origen        ENUM('cliente','mesero') NOT NULL DEFAULT 'mesero',
    creado_por    INT NULL,
    estado        ENUM('pendiente','preparacion','listo','entregado','cancelado') NOT NULL DEFAULT 'pendiente',
    creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    iniciado_en   DATETIME NULL,
    listo_en      DATETIME NULL,
    entregado_en  DATETIME NULL,
    FOREIGN KEY (cuenta_id) REFERENCES cuentas(id) ON DELETE CASCADE,
    INDEX idx_comanda_estado (estado, creado_en)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS comanda_items (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    comanda_id          INT NOT NULL,
    cuenta_id           INT NOT NULL,
    producto_id         INT NULL,
    nombre              VARCHAR(150) NOT NULL,            -- snapshot
    categoria           VARCHAR(80)  NULL,                -- snapshot
    precio              DECIMAL(10,2) NOT NULL,           -- snapshot
    costo               DECIMAL(10,2) NOT NULL DEFAULT 0, -- snapshot
    cantidad            INT NOT NULL,
    notas               VARCHAR(255) NULL,
    estado              ENUM('activo','cancelado') NOT NULL DEFAULT 'activo',
    motivo_cancelacion  VARCHAR(255) NULL,
    FOREIGN KEY (comanda_id)  REFERENCES comandas(id) ON DELETE CASCADE,
    FOREIGN KEY (cuenta_id)   REFERENCES cuentas(id)  ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL,
    INDEX idx_item_cuenta (cuenta_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pagos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    cuenta_id       INT NOT NULL,
    turno_caja_id   INT NULL,
    cobrado_por     INT NULL,
    monto           DECIMAL(10,2) NOT NULL,
    propina         DECIMAL(10,2) NOT NULL DEFAULT 0,
    metodo          ENUM('Efectivo','Tarjeta','Transferencia') NOT NULL DEFAULT 'Efectivo',
    recibido        DECIMAL(10,2) NULL,        -- efectivo entregado por el cliente
    etiqueta        VARCHAR(60)   NULL,        -- "Persona 1"
    items           LONGTEXT      NULL,        -- JSON [{item_id, cantidad}]
    anulado         TINYINT(1)    NOT NULL DEFAULT 0,
    anulado_motivo  VARCHAR(255)  NULL,
    anulado_por     INT NULL,
    creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cuenta_id) REFERENCES cuentas(id) ON DELETE CASCADE,
    INDEX idx_pago_fecha (creado_en),
    INDEX idx_pago_turno (turno_caja_id)
) ENGINE=InnoDB;

-- ── Llamados desde la mesa ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS llamados (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    mesa_id       INT NOT NULL,
    tipo          ENUM('mesero','cuenta') NOT NULL,
    metodo_pago   ENUM('efectivo','tarjeta','dividir') NULL,
    estado        ENUM('pendiente','atendido','cancelado') NOT NULL DEFAULT 'pendiente',
    creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atendido_en   DATETIME NULL,
    atendido_por  INT NULL,
    FOREIGN KEY (mesa_id) REFERENCES mesas(id) ON DELETE CASCADE,
    INDEX idx_llamado_estado (estado, creado_en)
) ENGINE=InnoDB;

-- ── Inventario ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS compras (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    proveedor       VARCHAR(120) NOT NULL,
    producto_id     INT NOT NULL,
    cantidad        INT NOT NULL,
    costo_unitario  DECIMAL(10,2) NOT NULL,
    total           DECIMAL(10,2) NOT NULL,
    notas           VARCHAR(255) NULL,
    registrado_por  INT NULL,
    fecha           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    INDEX idx_compra_fecha (fecha)
) ENGINE=InnoDB;

-- ── Auditoría ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS auditoria (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT NULL,
    usuario_nombre  VARCHAR(120) NULL,
    accion          VARCHAR(60)  NOT NULL,
    categoria       ENUM('venta','producto','acceso','personal','caja','inventario','sistema') NOT NULL DEFAULT 'sistema',
    entidad         VARCHAR(40)  NULL,
    entidad_id      VARCHAR(40)  NULL,
    descripcion     VARCHAR(255) NULL,
    detalle         LONGTEXT     NULL,
    ip              VARCHAR(45)  NULL,
    creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_aud_fecha (creado_en),
    INDEX idx_aud_accion (accion),
    INDEX idx_aud_cat (categoria)
) ENGINE=InnoDB;

-- ── Turnos de trabajo ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS turnos (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    empleado_id  INT NOT NULL,
    fecha        DATE NOT NULL,
    hora_inicio  TIME NOT NULL,
    hora_fin     TIME NOT NULL,                 -- si es menor que inicio, termina al día siguiente
    tipo         ENUM('manana','tarde','noche','personalizado') NOT NULL DEFAULT 'personalizado',
    publicado    TINYINT(1) NOT NULL DEFAULT 0,
    notas        VARCHAR(255) NULL,
    creado_en    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
    INDEX idx_turno_fecha (fecha, empleado_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS plantillas_turno (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nombre     VARCHAR(80) NOT NULL,
    patron     LONGTEXT NOT NULL,   -- JSON: [{empleado_id, dia(0-6), hora_inicio, hora_fin, tipo}]
    rotativa   TINYINT(1) NOT NULL DEFAULT 0, -- 1: al aplicar, rota los patrones entre empleados cada semana
    creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Asistencia ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS marcaciones (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    empleado_id   INT NOT NULL,
    tipo          ENUM('entrada','inicio_descanso','fin_descanso','salida') NOT NULL,
    marcado_en    DATETIME NOT NULL,
    origen        ENUM('kiosco','perfil','correccion') NOT NULL DEFAULT 'kiosco',
    foto          VARCHAR(255) NULL,
    corregido_por INT NULL,
    motivo        VARCHAR(255) NULL,
    creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
    INDEX idx_marca_emp (empleado_id, marcado_en)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS justificaciones (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    empleado_id   INT NOT NULL,
    fecha         DATE NOT NULL,
    tipo          ENUM('falta','tardanza','salida_anticipada') NOT NULL DEFAULT 'falta',
    motivo        VARCHAR(255) NOT NULL,
    archivo       VARCHAR(255) NULL,
    registrado_por INT NULL,
    creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
    UNIQUE KEY uq_just (empleado_id, fecha, tipo)
) ENGINE=InnoDB;

-- ── Solicitudes ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS solicitudes (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    empleado_id    INT NOT NULL,
    tipo           ENUM('vacaciones','permiso','cambio_turno') NOT NULL,
    fecha_inicio   DATE NULL,
    fecha_fin      DATE NULL,
    turno_id       INT NULL,          -- cambio_turno: turno propio
    companero_id   INT NULL,          -- cambio_turno: quien lo toma
    turno_companero_id INT NULL,      -- cambio_turno: turno que se recibe a cambio (opcional)
    motivo         VARCHAR(255) NULL,
    estado         ENUM('pendiente','aprobada','rechazada','cancelada') NOT NULL DEFAULT 'pendiente',
    respuesta      VARCHAR(255) NULL,
    resuelto_por   INT NULL,
    resuelto_en    DATETIME NULL,
    creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
    INDEX idx_sol_estado (estado)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ════════════════════════════════════════════════════════════════════════
--  EL CUACHE — Datos iniciales
--  Usuarios de prueba (cámbialos al entrar):
--    admin   / cuache2026   PIN 1234   (Administrador)
--    carla   / mesero123    PIN 2345   (Mesera)
--    luis    / cocina123    PIN 3456   (Cocinero)
--    diego   / equipo123    PIN 5678   (Mesero)
--  Sin acceso al sistema (solo kiosco): Sofía 4567 · María 6789 · Jorge 7890
--  Los PIN se guardan como HMAC con app.pin_secret (Backend/config/app.php).
-- ════════════════════════════════════════════════════════════════════════

INSERT INTO configuracion (clave, valor) VALUES
('restaurante_nombre', 'El Cuache'),
('restaurante_lema',   'Café y Restaurante'),
('moneda',             'Q'),
('url_base_menu',      ''),          -- vacío = se usa la URL desde donde se abre el sistema
('propina_sugerida',   '10'),
('horas_max_semana',   '48'),
('descanso_min_horas', '10'),
('tolerancia_minutos', '5'),
('cobertura_minima',   '2'),
('alerta_doc_dias',    '30'),
('horario_apertura',   '07:00'),
('horario_cierre',     '23:00')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

INSERT INTO empleados
 (id, nombre, apellido, documento_identidad, fecha_nacimiento, telefono, email, direccion,
  emergencia_nombre, emergencia_parentesco, emergencia_telefono, puesto, fecha_ingreso,
  tipo_contrato, tipo_pago, salario, tarifa_hora, banco, cuenta_bancaria,
  talla_camisa, talla_pantalon, talla_calzado, notas_medicas, estado, dias_vacaciones,
  acceso_activo, usuario, password, rol, pin_hash) VALUES
(1, 'Andrea', 'Morales', '2541 87412 0101', '1990-03-14', '5512-3344', 'admin@elcuache.gt', 'Zona 10, Ciudad de Guatemala',
 'Roberto Morales', 'Hermano', '5544-1122', 'Administrador', '2023-01-09',
 'Indefinido', 'Mensual', 9500, 0, 'Banco Industrial', '000-123456-7',
 'M', '30', '38', NULL, 'activo', 15,
 1, 'admin', '$2y$10$Nv3PZBxTXdaBQEQW6YOkX.MTcDXmXoFOiTY585s5biZy309WdkL2G', 'Admin',
 '774575639f5f42d73f7e9aee2e412a890b4488904d22388be084a659ea583954'),
(2, 'Carla', 'Méndez', '2987 11234 0101', DATE_ADD(DATE_SUB(CURDATE(), INTERVAL 24 YEAR), INTERVAL 3 DAY), '4433-2211', 'carla@elcuache.gt', 'Mixco, Guatemala',
 'Ana Méndez', 'Madre', '4455-6677', 'Mesero', '2024-05-02',
 'Indefinido', 'Mensual', 3800, 0, 'Banrural', '310-002233-1',
 'S', '28', '37', 'Alergia a la penicilina', 'activo', 15,
 1, 'carla', '$2y$10$.u2FeFAfh.Z7SbKOD6Z3POgsHAbpJXSjHqAE6ZNzC8wfRZoLxI2Nm', 'Mesero',
 'd5cf37e85c6b3b8d469e55281bab29a55fb213eaad2a879779178a730873845e'),
(3, 'Luis', 'Ortiz', '3011 44556 0101', '1988-07-22', '5566-7788', 'luis@elcuache.gt', 'Villa Nueva, Guatemala',
 'Marta de Ortiz', 'Esposa', '5566-0000', 'Cocinero', '2023-06-15',
 'Indefinido', 'Mensual', 5200, 0, 'BAM', '401-998877-2',
 'L', '34', '42', NULL, 'activo', 15,
 1, 'luis', '$2y$10$CPTH785B2vrBfg7qtI87geRgSfAj3qJAi3BLeLkqxMaAuav6Eg/vi', 'Cocinero',
 'e906c860751729ef4147dc9996c7093a513d68df60411feab5a6728197e43c67'),
(4, 'Sofía', 'Ramírez', '2876 33445 0101', '1998-11-02', '3322-1100', NULL, 'Zona 1, Ciudad de Guatemala',
 'Pedro Ramírez', 'Padre', '3322-9988', 'Bartender', '2025-02-10',
 'Temporal', 'Por hora', 0, 28, 'Banrural', '310-445566-0',
 'S', '26', '36', NULL, 'activo', 10,
 0, NULL, NULL, NULL,
 '3cd89523b638e4fad9d9b3ab17789772ec5be17481dfb6cbc6fdb796f5c4152c'),
(5, 'Diego', 'Castillo', '3102 77889 0101', '2000-01-28', '4210-5566', 'diego@elcuache.gt', 'Zona 7, Ciudad de Guatemala',
 'Lucía Castillo', 'Hermana', '4210-1111', 'Mesero', '2025-08-01',
 'Prueba', 'Mensual', 3600, 0, 'Banco Industrial', '000-778899-3',
 'M', '30', '40', NULL, 'activo', 15,
 1, 'diego', '$2y$10$gQK.Zz3j.W1qlUhv7/0eReHuzWzdrlvh8B9IFXLhZSNFXPVawmhFO', 'Mesero',
 '3dbb76da701f77992924e46c2224bbb14d32bc568da65160db885cac3ad752b5'),
(6, 'María', 'López', '2765 99001 0101', '1993-05-19', '5098-7766', 'maria@elcuache.gt', 'San Miguel Petapa',
 'José López', 'Esposo', '5098-0011', 'Cajero', '2024-01-15',
 'Indefinido', 'Mensual', 4200, 0, 'BAM', '401-112233-9',
 'M', '29', '37', NULL, 'vacaciones', 15,
 0, NULL, NULL, NULL,
 'f5152f87ef9a370f546ba4b5cb4d848d9b9550ae85306201ddba084bafd53105'),
(7, 'Jorge', 'Pérez', '3201 22113 0101', '2002-09-09', '3011-2233', NULL, 'Zona 18, Ciudad de Guatemala',
 'Rosa Pérez', 'Madre', '3011-0099', 'Ayudante', '2025-04-21',
 'Por horas', 'Por hora', 0, 22, NULL, NULL,
 'M', '30', '41', 'Asma leve — usa inhalador', 'activo', 10,
 0, NULL, NULL, NULL,
 'ef59f9aa345160a18e4a0b5defd8360e69baf2ad51aa58c24ee16e7ac0494813');

-- Documento que vence pronto (para la alerta)
INSERT INTO empleado_documentos (empleado_id, tipo, nombre, archivo, mime, vence_en) VALUES
(3, 'Certificado de salud', 'Tarjeta de salud 2026', 'storage/demo/tarjeta-salud.pdf', 'application/pdf', DATE_ADD(CURDATE(), INTERVAL 12 DAY)),
(2, 'Contrato', 'Contrato laboral', 'storage/demo/contrato.pdf', 'application/pdf', NULL);

INSERT INTO categorias (id, nombre, icono, orden) VALUES
(1, 'Desayunos',      'Egg',        1),
(2, 'Cafetería',      'Coffee',     2),
(3, 'Entradas',       'Bowl',       3),
(4, 'Platos fuertes', 'ForkKnife',  4),
(5, 'Postres',        'Cake',       5),
(6, 'Bebidas',        'Martini',    6);

INSERT INTO productos (categoria_id, nombre, descripcion, precio, costo, imagen, imagen_posicion, controla_stock, stock, stock_minimo, destacado) VALUES
(1, 'Desayuno chapín',       'Huevos al gusto, frijoles volteados, plátanos fritos, crema, queso fresco y tortillas de maíz.', 45, 16, 'img/desayuno.jpg', '50% 50%', 0, 0, 5, 1),
(1, 'Huevos benedictinos',   'Muffin inglés, jamón glaseado, huevo pochado y holandesa de la casa.',                          55, 19, 'img/desayuno.jpg', '72% 55%', 0, 0, 5, 0),
(1, 'Pan de aguacate',       'Masa madre tostada, aguacate machacado, tomate cherry, queso feta y eneldo.',                   48, 15, 'img/desayuno.jpg', '35% 55%', 0, 0, 5, 0),
(1, 'Pancakes de la abuela', 'Tres pancakes esponjosos, miel de maple, mantequilla batida y frutos rojos.',                   40, 11, 'img/postre.jpg',   '50% 50%', 0, 0, 5, 0),
(2, 'Espresso',              'Café de altura de Antigua, extracción corta e intensa.',                                        15,  4, 'img/bebida.jpg',   '50% 30%', 0, 0, 5, 0),
(2, 'Capuchino',             'Espresso doble, leche texturizada y cacao.',                                                   22,  6, 'img/bebida.jpg',   '50% 40%', 0, 0, 5, 1),
(2, 'Café de olla',          'Con canela, piloncillo y un toque de clavo, como en casa.',                                     18,  5, 'img/bebida.jpg',   '50% 60%', 0, 0, 5, 0),
(2, 'Frappé de caramelo',    'Café frío batido con caramelo salado y crema.',                                                32,  9, 'img/bebida.jpg',   '50% 50%', 0, 0, 5, 0),
(3, 'Nachos de la casa',     'Totopos, frijol, queso fundido, pico de gallo, guacamole y crema.',                             45, 14, 'img/almuerzo.jpg', '50% 50%', 0, 0, 5, 0),
(3, 'Alitas BBQ',            'Ocho alitas glaseadas en BBQ ahumada con aderezo ranch.',                                       55, 20, 'img/almuerzo.jpg', '30% 50%', 0, 0, 5, 0),
(4, 'Pepián de pollo',       'Recado tradicional de semillas tostadas, güisquil, papa y arroz.',                              68, 22, 'img/almuerzo.jpg', '50% 50%', 0, 0, 5, 1),
(4, 'Lomito a la parrilla',  'Lomito de res 8 oz, chimichurri, papas rústicas y vegetales asados.',                          95, 38, 'img/almuerzo.jpg', '65% 45%', 0, 0, 5, 0),
(4, 'Pasta Alfredo con pollo','Fettuccine en salsa cremosa de parmesano con pollo a la plancha.',                            72, 21, 'img/almuerzo.jpg', '50% 70%', 0, 0, 5, 0),
(4, 'Hamburguesa El Cuache', 'Carne angus, queso cheddar, tocino, cebolla caramelizada y papas.',                            65, 24, 'img/almuerzo.jpg', '50% 50%', 0, 0, 5, 0),
(5, 'Rellenitos de plátano', 'Plátano relleno de frijol dulce con chocolate y canela.',                                       25,  7, 'img/postre.jpg',   '50% 50%', 0, 0, 5, 0),
(5, 'Cheesecake de frutos rojos','Base de galleta, queso crema horneado y coulis de frutos rojos.',                          35, 11, 'img/postre.jpg',   '40% 40%', 1, 8, 3, 1),
(5, 'Brownie con helado',    'Brownie tibio de chocolate semiamargo con helado de vainilla.',                                 32, 10, 'img/postre.jpg',   '60% 60%', 0, 0, 5, 0),
(6, 'Limonada con hierbabuena','Limón recién exprimido, hierbabuena y hielo frappé.',                                        20,  4, 'img/bebida.jpg',   '50% 50%', 0, 0, 5, 0),
(6, 'Licuado de fresa',      'Fresas naturales con leche o agua.',                                                            22,  6, 'img/bebida.jpg',   '50% 50%', 0, 0, 5, 0),
(6, 'Agua pura',             'Botella 600 ml.',                                                                               10,  3, 'img/bebida.jpg',   '50% 50%', 1, 40, 12, 0),
(6, 'Gaseosa',               'Lata 355 ml.',                                                                                  12,  5, 'img/bebida.jpg',   '50% 50%', 1, 0, 12, 0);

-- La gaseosa arranca agotada por stock (demostración del badge "Sin stock")
UPDATE productos SET disponible = 0, agotado_motivo = 'stock', agotado_en = NOW() WHERE nombre = 'Gaseosa';

INSERT INTO mesas (numero, capacidad, zona, forma, pos_x, pos_y, qr_token) VALUES
( 1, 2, 'Salón',   'redonda',  8, 12, '12d3b48da210c8093e6e7c18'),
( 2, 2, 'Salón',   'redonda', 30, 12, '3628f7c405dd8b5a531a770d'),
( 3, 4, 'Salón',   'cuadrada',52, 12, '0b216ec682d155154d366199'),
( 4, 4, 'Salón',   'cuadrada', 8, 44, 'd7343259e33cf41cc8879cae'),
( 5, 4, 'Salón',   'cuadrada',30, 44, '6bdf01bba2e20a9f0bfbd563'),
( 6, 8, 'Salón',   'larga',   52, 44, '7d16101f1341bc3b2b8e4cf2'),
( 7, 4, 'Terraza', 'redonda', 80, 12, 'f33f5947ee370ff605bba4e1'),
( 8, 4, 'Terraza', 'redonda', 80, 40, 'a39ebae1434fab819f8137d0'),
( 9, 2, 'Terraza', 'redonda', 80, 68, '7de7086a6f5444f873a0bc19'),
(10, 6, 'Salón',   'larga',   20, 74, '2dff1907e78c9e1f10db7b84');

-- Turnos publicados de la semana actual (lunes = WEEKDAY 0)
SET @lunes = DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY);
INSERT INTO turnos (empleado_id, fecha, hora_inicio, hora_fin, tipo, publicado)
SELECT e.id, DATE_ADD(@lunes, INTERVAL d.n DAY), t.ini, t.fin, t.tipo, 1
FROM (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6) d
JOIN empleados e
JOIN (SELECT 1 eid, '07:00:00' ini, '15:00:00' fin, 'manana' tipo
      UNION SELECT 2, '07:00:00', '15:00:00', 'manana'
      UNION SELECT 3, '07:00:00', '15:00:00', 'manana'
      UNION SELECT 4, '15:00:00', '23:00:00', 'tarde'
      UNION SELECT 5, '15:00:00', '23:00:00', 'tarde'
      UNION SELECT 7, '15:00:00', '23:00:00', 'tarde') t ON t.eid = e.id
-- cada quien descansa un día distinto
WHERE NOT (e.id = 1 AND d.n = 6) AND NOT (e.id = 2 AND d.n = 2) AND NOT (e.id = 3 AND d.n = 3)
  AND NOT (e.id = 4 AND d.n = 0) AND NOT (e.id = 5 AND d.n = 1) AND NOT (e.id = 7 AND d.n = 4);

INSERT INTO plantillas_turno (nombre, patron, rotativa) VALUES
-- Rotativa: cada semana los patrones se rotan entre los empleados de la plantilla
-- (Carla hace mañana y Diego tarde; la semana siguiente se invierten).
('Meseros · rotación mañana/tarde', '[{"empleado_id":2,"dia":0,"hora_inicio":"07:00","hora_fin":"15:00","tipo":"manana"},{"empleado_id":2,"dia":1,"hora_inicio":"07:00","hora_fin":"15:00","tipo":"manana"},{"empleado_id":2,"dia":3,"hora_inicio":"07:00","hora_fin":"15:00","tipo":"manana"},{"empleado_id":2,"dia":4,"hora_inicio":"07:00","hora_fin":"15:00","tipo":"manana"},{"empleado_id":2,"dia":5,"hora_inicio":"07:00","hora_fin":"15:00","tipo":"manana"},{"empleado_id":5,"dia":0,"hora_inicio":"15:00","hora_fin":"23:00","tipo":"tarde"},{"empleado_id":5,"dia":2,"hora_inicio":"15:00","hora_fin":"23:00","tipo":"tarde"},{"empleado_id":5,"dia":3,"hora_inicio":"15:00","hora_fin":"23:00","tipo":"tarde"},{"empleado_id":5,"dia":4,"hora_inicio":"15:00","hora_fin":"23:00","tipo":"tarde"},{"empleado_id":5,"dia":5,"hora_inicio":"15:00","hora_fin":"23:00","tipo":"tarde"}]', 1);

INSERT INTO solicitudes (empleado_id, tipo, fecha_inicio, fecha_fin, motivo) VALUES
(2, 'vacaciones', DATE_ADD(CURDATE(), INTERVAL 20 DAY), DATE_ADD(CURDATE(), INTERVAL 24 DAY), 'Viaje familiar a Xela'),
(5, 'permiso',    DATE_ADD(CURDATE(), INTERVAL 3 DAY),  DATE_ADD(CURDATE(), INTERVAL 3 DAY),  'Cita médica en la mañana');
