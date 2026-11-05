<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=back_to_home_db;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $ex) {
    die('No se pudo conectar. Enciende MySQL en XAMPP e importa database.sql.');
}

function e($t) { return htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8'); }
function opciones($lista, $sel = '') {
    foreach ($lista as $x) echo '<option' . ($x === $sel ? ' selected' : '') . '>' . e($x) . '</option>';
}

function subirImagen($campo) {
    if (empty($_FILES[$campo]['name'])) return null;
    $f = $_FILES[$campo];
    if ($f['error'] !== UPLOAD_ERR_OK) throw new Exception('No se pudo subir la imagen.');
    if ($f['size'] > 2 * 1024 * 1024) throw new Exception('La imagen no debe pasar de 2 MB.');
    $info  = @getimagesize($f['tmp_name']);
    $tipos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!$info || !isset($tipos[$info['mime']])) throw new Exception('Solo se permiten imágenes JPG, PNG o WEBP.');
    if (!is_dir('uploads')) mkdir('uploads', 0755, true);
    $nombre = bin2hex(random_bytes(8)) . '.' . $tipos[$info['mime']];
    move_uploaded_file($f['tmp_name'], "uploads/$nombre");
    return $nombre;
}
function tieneFoto($archivo) { return !empty($archivo) && is_file("uploads/$archivo"); }

function avatar($archivo, $nombre, $clase = '') {
    if (tieneFoto($archivo)) return '<img class="avatar ' . $clase . '" src="uploads/' . e($archivo) . '" alt="Foto de ' . e($nombre) . '" loading="lazy">';
    return '<span class="avatar avatar-ini ' . $clase . '">' . e(mb_strtoupper(mb_substr(trim($nombre), 0, 1))) . '</span>';
}

function mapsUrl($zona, $estado) {
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(trim(($zona ? "$zona, " : '') . "$estado, México"));
}

$estados    = ['Aguascalientes','Baja California','Baja California Sur','Campeche','Chiapas','Chihuahua','Ciudad de México','Coahuila','Colima','Durango','Guanajuato','Guerrero','Hidalgo','Jalisco','México','Michoacán','Morelos','Nayarit','Nuevo León','Oaxaca','Puebla','Querétaro','Quintana Roo','San Luis Potosí','Sinaloa','Sonora','Tabasco','Tamaulipas','Tlaxcala','Veracruz','Yucatán','Zacatecas'];
$horarios   = ['Madrugada', 'Mañana', 'Tarde', 'Noche'];
$delitos    = ['Robo a transeúnte', 'Robo de vehículo', 'Robo a casa habitación', 'Robo a negocio', 'Lesiones', 'Otro'];
$categorias = ['Emergencia', 'Búsqueda', 'Atención psicológica', 'Asesoría legal', 'Derechos humanos', 'Otro'];

$vistas = ['registrar' => 'Registrar alerta', 'fichas' => 'Fichas', 'colectivos' => 'Colectivos', 'estadisticas' => 'Estadísticas', 'apoyos' => 'Apoyos'];
$vista = $_GET['vista'] ?? 'fichas';
if (!isset($vistas[$vista])) $vista = 'fichas';

$destinos = ['ficha' => 'fichas', 'colectivo' => 'colectivos', 'reporte' => 'estadisticas', 'programa' => 'apoyos'];
$avisos = ['ficha' => '¡Ficha registrada con éxito!', 'colectivo' => '¡Colectivo registrado con éxito!', 'reporte' => '¡Reporte de zona registrado con éxito!', 'programa' => '¡Programa agregado con éxito!'];

$mensaje = '';
$tipoMsg = 'ok';
$esPost = $_SERVER['REQUEST_METHOD'] === 'POST';
if (isset($avisos[$_GET['ok'] ?? ''])) $mensaje = $avisos[$_GET['ok']];

if ($esPost) {
    try {
        $accion = $_POST['accion'] ?? '';
        if (!isset($destinos[$accion])) throw new Exception('Acción no válida.');
        $estado = $_POST['estado'] ?? '';
        if ($accion !== 'programa' && !in_array($estado, $estados, true)) throw new Exception('Selecciona un estado válido.');

        if ($accion === 'ficha') {
            $nombre   = trim($_POST['nombre'] ?? '');
            $edad     = (int)($_POST['edad'] ?? -1);
            $lugar    = trim($_POST['lugar'] ?? '');
            $fecha    = $_POST['fecha'] ?? '';
            $contacto = preg_replace('/[^0-9+ ()-]/', '', $_POST['contacto'] ?? '');
            if ($nombre === '' || $lugar === '' || $contacto === '') throw new Exception('Completa los campos obligatorios.');
            if ($edad < 0 || $edad > 120) throw new Exception('La edad no es válida.');
            if ($fecha === '' || $fecha > date('Y-m-d')) throw new Exception('La fecha no puede estar vacía ni ser futura.');
            $imagen = subirImagen('imagen');
            $pdo->prepare('INSERT INTO fichas (estado, nombre, edad, lugar_desaparicion, fecha_desaparicion, contacto, imagen) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$estado, $nombre, $edad, $lugar, $fecha, $contacto, $imagen]);

        } elseif ($accion === 'colectivo') {
            $nombrecolec = trim($_POST['nombrecolec'] ?? '');
            if ($nombrecolec === '') throw new Exception('Escribe el nombre del colectivo.');
            $imagen = subirImagen('imagen');
            $pdo->prepare('INSERT INTO colectivos (estado, nombrecolec, imagen) VALUES (?, ?, ?)')->execute([$estado, $nombrecolec, $imagen]);

        } elseif ($accion === 'reporte') {
            $zonaTxt = trim($_POST['zona'] ?? '');
            $horario = $_POST['horario'] ?? '';
            $delito  = $_POST['delito'] ?? '';
            $fecha   = $_POST['fecha'] ?? '';
            if ($zonaTxt === '') throw new Exception('Escribe la colonia o zona del reporte.');
            if (!in_array($horario, $horarios, true) || !in_array($delito, $delitos, true)) throw new Exception('Elige un horario y un tipo de reporte válidos.');
            if ($fecha === '' || $fecha > date('Y-m-d')) throw new Exception('La fecha no puede estar vacía ni ser futura.');
            $pdo->prepare('INSERT INTO estadisticas (estado, zona, horario, tipo_delito, total, fecha_reporte) VALUES (?, ?, ?, ?, 1, ?)')
                ->execute([$estado, $zonaTxt, $horario, $delito, $fecha]);

        } else {
            $nombre      = trim($_POST['nombre'] ?? '');
            $categoria   = $_POST['categoria'] ?? '';
            $descripcion = trim($_POST['descripcion'] ?? '');
            $contacto    = trim($_POST['contacto'] ?? '');
            if ($nombre === '' || $descripcion === '' || $contacto === '') throw new Exception('Completa los campos obligatorios.');
            if (!in_array($categoria, $categorias, true)) throw new Exception('Elige una categoría válida.');
            $imagen = subirImagen('imagen');
            $pdo->prepare('INSERT INTO programas (nombre, categoria, descripcion, contacto, imagen) VALUES (?, ?, ?, ?, ?)')
                ->execute([$nombre, $categoria, $descripcion, $contacto, $imagen]);
        }
        header("Location: index.php?vista={$destinos[$accion]}&ok=$accion");
        exit;
    } catch (PDOException $ex) {
        $mensaje = 'Error al guardar en la base de datos.'; $tipoMsg = 'error';
    } catch (Exception $ex) {
        $mensaje = $ex->getMessage(); $tipoMsg = 'error';
    }
}

$filtro = $_GET['estado'] ?? '';
$buscar = trim($_GET['q'] ?? '');
$st = $pdo->prepare('SELECT * FROM fichas WHERE (? = "" OR estado = ?) AND (? = "" OR nombre LIKE ?) ORDER BY fecha_registro DESC');
$st->execute([$filtro, $filtro, $buscar, "%$buscar%"]);
$fichas = $st->fetchAll();

$colectivos = $pdo->query('SELECT * FROM colectivos ORDER BY estado, nombrecolec')->fetchAll();

$st = $pdo->prepare('SELECT horario, SUM(total) AS total FROM estadisticas WHERE (? = "" OR estado = ?) GROUP BY horario ORDER BY FIELD(horario, "Madrugada", "Mañana", "Tarde", "Noche")');
$st->execute([$filtro, $filtro]);
$stats = $st->fetchAll();
$max = max(array_column($stats, 'total') ?: [1]);

$st = $pdo->prepare('SELECT * FROM estadisticas WHERE zona IS NOT NULL AND (? = "" OR estado = ?) ORDER BY fecha_reporte DESC, id DESC LIMIT 20');
$st->execute([$filtro, $filtro]);
$reportes = $st->fetchAll();

$programas = $pdo->query('SELECT * FROM programas ORDER BY categoria, nombre')->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Back to Home - Sistema de Alerta Temprana</title>
    <link rel="stylesheet" href="diseño.css">
    <link rel="stylesheet" href="extras.css">
</head>
<body>
    <header>
        <div class="container">
            <img src="logo.svg" alt="Logo de Back to Home" class="logo">
            <h1>Back to Home</h1>
            <p>Prototipo de Software</p>
        </div>
    </header>
    <nav>
        <?php foreach ($vistas as $clave => $texto): ?>
            <a href="index.php?vista=<?= $clave ?>" class="<?= $vista === $clave ? 'activa' : '' ?>"><?= e($texto) ?></a>
        <?php endforeach; ?>
    </nav>

    <main class="container">
        <?php if ($mensaje !== ''): ?>
            <div class="alert <?= $tipoMsg ?>"><?= e($mensaje) ?></div>
        <?php endif; ?>

        <?php if ($vista === 'registrar'): ?>
        <section class="form-section" id="registrar">
            <h2>Registro de Alerta de Persona No Localizada</h2>
            <form action="index.php?vista=registrar" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="ficha">
                <div class="form-group">
                    <label for="nombre">Nombre completo de la persona:</label>
                    <input type="text" id="nombre" name="nombre" required placeholder="Ej. Huitron Vargas Rafael">
                </div>
                <div class="form-group">
                    <label for="estado">Estado:</label>
                    <select id="estado" name="estado" required>
                        <option value="">Selecciona un estado</option>
                        <?php opciones($estados); ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edad">Edad:</label>
                    <input type="number" id="edad" name="edad" min="0" max="120" required placeholder="Ej. 67">
                </div>
                <div class="form-group">
                    <label for="lugar">Último lugar visto:</label>
                    <input type="text" id="lugar" name="lugar" required placeholder="Ej. Colonia Centro, Municipio...">
                    <small>Escribe solo la colonia o zona. No publiques domicilios particulares.</small>
                </div>
                <div class="form-group">
                    <label for="fecha">Fecha de desaparición:</label>
                    <input type="date" id="fecha" name="fecha" max="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label for="contacto">Teléfono de emergencia / contacto:</label>
                    <input type="text" id="contacto" name="contacto" required placeholder="Ej. 2221234567">
                </div>
                <div class="form-group">
                    <label for="imagen">Fotografía (opcional):</label>
                    <input type="file" id="imagen" name="imagen" accept="image/jpeg,image/png,image/webp">
                    <small>JPG, PNG o WEBP, máximo 2 MB.</small>
                </div>
                <button type="submit" class="btn-submit">Generar ficha y registrar</button>
            </form>
        </section>
        <?php endif; ?>

        <?php if ($vista === 'fichas'): ?>
        <section class="form-section" id="fichas">
            <h2>Fichas de Búsqueda Activas</h2>
            <form class="filtros" action="index.php" method="GET">
                <input type="hidden" name="vista" value="fichas">
                <input type="text" name="q" value="<?= e($buscar) ?>" placeholder="Buscar por nombre">
                <select name="estado">
                    <option value="">Todos los estados</option>
                    <?php opciones($estados, $filtro); ?>
                </select>
                <button type="submit" class="btn-submit">Buscar</button>
            </form>
            <?php if (count($fichas) > 0): ?>
                <div class="grid-fichas">
                    <?php foreach ($fichas as $f): ?>
                        <div class="card-ficha">
                            <?php if (tieneFoto($f['imagen'] ?? null)): ?>
                                <img class="foto-ficha" src="uploads/<?= e($f['imagen']) ?>" alt="Foto de <?= e($f['nombre']) ?>" loading="lazy">
                            <?php endif; ?>
                            <span class="badge"><?= e($f['estado']) ?></span>
                            <h3><?= e($f['nombre']) ?></h3>
                            <p><strong>Edad:</strong> <?= e($f['edad']) ?> años</p>
                            <p><strong>Último lugar visto:</strong> <?= e($f['lugar_desaparicion']) ?></p>
                            <p><strong>Fecha de desaparición:</strong> <?= e($f['fecha_desaparicion']) ?></p>
                            <p class="contacto-urgente"><strong>Contacto:</strong> <?= e($f['contacto']) ?></p>
                            <span class="fecha-registro">Registrado el: <?= e($f['fecha_registro']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-fichas">No hay fichas que coincidan con tu búsqueda.</p>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($vista === 'colectivos'): ?>
        <section class="form-section" id="colectivos">
            <h2>Colectivos de Búsqueda</h2>
            <form action="index.php?vista=colectivos" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="colectivo">
                <div class="form-group">
                    <label for="c-nombre">Nombre del colectivo:</label>
                    <input type="text" id="c-nombre" name="nombrecolec" required placeholder="Ej. Colectivo Esperanza">
                </div>
                <div class="form-group">
                    <label for="c-estado">Estado:</label>
                    <select id="c-estado" name="estado" required>
                        <option value="">Selecciona un estado</option>
                        <?php opciones($estados); ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="c-imagen">Foto de perfil (opcional):</label>
                    <input type="file" id="c-imagen" name="imagen" accept="image/jpeg,image/png,image/webp">
                    <small>JPG, PNG o WEBP, máximo 2 MB.</small>
                </div>
                <button type="submit" class="btn-submit">Registrar colectivo</button>
            </form>
            <?php if (count($colectivos) > 0): ?>
                <ul class="lista">
                    <?php foreach ($colectivos as $c): ?>
                        <li>
                            <?= avatar($c['imagen'] ?? null, $c['nombrecolec']) ?>
                            <div><strong><?= e($c['nombrecolec']) ?></strong><br><span class="badge"><?= e($c['estado']) ?></span></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="no-fichas">Aún no hay colectivos registrados.</p>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($vista === 'estadisticas'): ?>
        <section class="form-section" id="estadisticas">
            <h2>Estadística por Zona y Horario</h2>
            <form class="filtros" action="index.php" method="GET">
                <input type="hidden" name="vista" value="estadisticas">
                <select name="estado">
                    <option value="">Todos los estados</option>
                    <?php opciones($estados, $filtro); ?>
                </select>
                <button type="submit" class="btn-submit">Ver</button>
            </form>
            <?php foreach ($stats as $s): ?>
                <div class="stat-fila">
                    <span><?= e($s['horario']) ?></span>
                    <div class="stat-barra"><div style="width: <?= round($s['total'] / $max * 100) ?>%"><?= e($s['total']) ?></div></div>
                </div>
            <?php endforeach; ?>
            <p class="nota">Cada reporte nuevo suma a la barra de su horario. Las cifras iniciales son de ejemplo.</p>

            <details class="agregar" <?= $esPost ? 'open' : '' ?>>
                <summary>Agregar reporte de una zona</summary>
                <form action="index.php?vista=estadisticas" method="POST">
                    <input type="hidden" name="accion" value="reporte">
                    <div class="form-group">
                        <label for="r-estado">Estado:</label>
                        <select id="r-estado" name="estado" required>
                            <option value="">Selecciona un estado</option>
                            <?php opciones($estados); ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="r-zona">Colonia o zona:</label>
                        <input type="text" id="r-zona" name="zona" required maxlength="255" placeholder="Ej. Colonia Centro, Puebla">
                    </div>
                    <div class="form-group">
                        <label for="r-horario">Horario:</label>
                        <select id="r-horario" name="horario" required><?php opciones($horarios); ?></select>
                    </div>
                    <div class="form-group">
                        <label for="r-delito">Tipo de reporte:</label>
                        <select id="r-delito" name="delito" required><?php opciones($delitos); ?></select>
                    </div>
                    <div class="form-group">
                        <label for="r-fecha">Fecha del suceso:</label>
                        <input type="date" id="r-fecha" name="fecha" max="<?= date('Y-m-d') ?>" required>
                    </div>
                    <button type="submit" class="btn-submit">Registrar reporte</button>
                </form>
            </details>

            <p class="subtitulo">Reportes por zona</p>
            <?php if (count($reportes) > 0): ?>
                <div class="grid-fichas">
                    <?php foreach ($reportes as $r): ?>
                        <div class="card-ficha">
                            <span class="badge"><?= e($r['estado']) ?></span>
                            <h3><?= e($r['zona']) ?></h3>
                            <p><strong>Tipo:</strong> <?= e($r['tipo_delito']) ?></p>
                            <p><strong>Horario:</strong> <?= e($r['horario']) ?></p>
                            <p><strong>Fecha:</strong> <?= e($r['fecha_reporte']) ?></p>
                            <a class="mapa-link" href="<?= e(mapsUrl($r['zona'], $r['estado'])) ?>" target="_blank" rel="noopener">📍 Ver en Google Maps</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-fichas">Aún no hay reportes de zonas.</p>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($vista === 'apoyos'): ?>
        <section class="form-section" id="apoyos">
            <h2>Programas de Gobierno y Apoyos</h2>
            <details class="agregar" <?= $esPost ? 'open' : '' ?>>
                <summary>Agregar un programa</summary>
                <form action="index.php?vista=apoyos" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="accion" value="programa">
                    <div class="form-group">
                        <label for="p-nombre">Nombre del programa:</label>
                        <input type="text" id="p-nombre" name="nombre" required maxlength="150">
                    </div>
                    <div class="form-group">
                        <label for="p-categoria">Categoría:</label>
                        <select id="p-categoria" name="categoria" required><?php opciones($categorias); ?></select>
                    </div>
                    <div class="form-group">
                        <label for="p-desc">Descripción:</label>
                        <input type="text" id="p-desc" name="descripcion" required maxlength="300">
                    </div>
                    <div class="form-group">
                        <label for="p-contacto">Contacto (teléfono o sitio oficial):</label>
                        <input type="text" id="p-contacto" name="contacto" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="p-imagen">Foto de perfil o logo (opcional):</label>
                        <input type="file" id="p-imagen" name="imagen" accept="image/jpeg,image/png,image/webp">
                        <small>JPG, PNG o WEBP, máximo 2 MB.</small>
                    </div>
                    <button type="submit" class="btn-submit">Agregar programa</button>
                </form>
            </details>
            <div class="grid-fichas">
                <?php foreach ($programas as $p): ?>
                    <div class="card-ficha">
                        <?= avatar($p['imagen'] ?? null, $p['nombre'], 'grande') ?><br>
                        <span class="badge"><?= e($p['categoria']) ?></span>
                        <h3><?= e($p['nombre']) ?></h3>
                        <p><?= e($p['descripcion']) ?></p>
                        <p class="contacto-urgente"><?= e($p['contacto']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <footer>Prototipo. No sustituye las investigaciones oficiales ni refleja la totalidad de los delitos.</footer>
</body>
</html>