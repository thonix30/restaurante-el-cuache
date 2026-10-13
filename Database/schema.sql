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

CREATE DATABASE IF NOT EXISTS db_elcuache CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_elcuache;

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
