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
USE db_elcuache;

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
