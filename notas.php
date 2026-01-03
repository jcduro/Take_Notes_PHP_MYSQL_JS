
 <!-- Welcome to
  
     |  ___|  __ \  |   |  _ \   _ \   
     | |      |   | |   | |   | |   |  
 \   | |      |   | |   | __ <  |   |  
\___/ \____| ____/ \___/ _| \_\\___/   
                                       
  ___|  _ \  __ \  ____|    _ )   _ _| __ \  ____|    \     ___|  
 |     |   | |   | __|     _ \ \   |  |   | __|     _ \  \___ \  
 |     |   | |   | |      ( `  <   |  |   | |      ___ \       | 
\____|\___/ ____/ _____| \___/\/ ___|____/ _____|_/    _\_____/  

  https://jcduro.bexartideas.com/index.php | 2026 | JC Duro Code & Ideas

------------------------------------------------------------------------------- -->


<?php include __DIR__ . '/includes/conexion.php'; ?>



<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>JcDuroDashBoard</title>
  
    <!-- Notas.css -->
    <link rel="stylesheet" href="/css/notas.css">

  </head>

<body>
<?php
// require_once __DIR__ . "/../../carpeta_conexion/archivo_conexion.php";

$mensaje = '';
$tipo_mensaje = '';

// CRUD OPERATIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // CREATE
    if ($accion === 'crear') {
        $nota = trim($_POST['nota'] ?? '');
        $prioridad = $_POST['prioridad'] ?? 'media';
        $categoria = $_POST['categoria'] ?? 'general';
        $etiquetas = trim($_POST['etiquetas'] ?? '');

        if (!empty($nota)) {
            try {
                $sql = "INSERT INTO notasjc (nota, prioridad, categoria, etiquetas) VALUES (?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nota, $prioridad, $categoria, $etiquetas]);

                $mensaje = "✓ Nota creada exitosamente";
                $tipo_mensaje = "success";
            } catch (PDOException $e) {
                $mensaje = "Error al crear la nota: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        } else {
            $mensaje = "La nota no puede estar vacía";
            $tipo_mensaje = "error";
        }
    }

    // UPDATE
    if ($accion === 'actualizar') {
        $cod_nota = (int)($_POST['cod_nota'] ?? 0);
        $nota = trim($_POST['nota'] ?? '');
        $prioridad = $_POST['prioridad'] ?? 'media';
        $categoria = $_POST['categoria'] ?? 'general';
        $etiquetas = trim($_POST['etiquetas'] ?? '');

        if ($cod_nota && !empty($nota)) {
            try {
                $sql = "UPDATE notasjc SET nota = ?, prioridad = ?, categoria = ?, etiquetas = ? WHERE cod_nota = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nota, $prioridad, $categoria, $etiquetas, $cod_nota]);

                $mensaje = "✓ Nota actualizada";
                $tipo_mensaje = "success";
            } catch (PDOException $e) {
                $mensaje = "Error al actualizar: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }
    }

    // TOGGLE COMPLETADA (AJAX)
    if ($accion === 'toggle_completada') {
        $cod_nota = (int)($_POST['cod_nota'] ?? 0);

        if ($cod_nota) {
            try {
                $sql = "UPDATE notasjc SET completada = NOT completada WHERE cod_nota = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$cod_nota]);

                echo json_encode(['success' => true]);
                exit;
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
        }
        echo json_encode(['success' => false]);
        exit;
    }

    // DELETE
    if ($accion === 'eliminar') {
        $cod_nota = (int)($_POST['cod_nota'] ?? 0);

        if ($cod_nota) {
            try {
                $sql = "DELETE FROM notasjc WHERE cod_nota = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$cod_nota]);

                $mensaje = "✓ Nota eliminada";
                $tipo_mensaje = "success";
            } catch (PDOException $e) {
                $mensaje = "Error al eliminar: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }
    }
}

// READ - Obtener notas con filtros
$filtro_completada = $_GET['completada'] ?? 'todos';
$filtro_prioridad = $_GET['prioridad'] ?? 'todos';
$filtro_categoria = $_GET['categoria'] ?? 'todos';
$busqueda = trim($_GET['busqueda'] ?? '');

$sql = "SELECT * FROM notasjc WHERE 1=1";
$params = [];

if ($filtro_completada !== 'todos') {
    $completada = ($filtro_completada === 'completadas') ? 1 : 0;
    $sql .= " AND completada = ?";
    $params[] = $completada;
}

if ($filtro_prioridad !== 'todos') {
    $sql .= " AND prioridad = ?";
    $params[] = $filtro_prioridad;
}

if ($filtro_categoria !== 'todos') {
    $sql .= " AND categoria = ?";
    $params[] = $filtro_categoria;
}

if (!empty($busqueda)) {
    $sql .= " AND (nota LIKE ? OR etiquetas LIKE ?)";
    $busqueda_pattern = "%$busqueda%";
    $params[] = $busqueda_pattern;
    $params[] = $busqueda_pattern;
}

$sql .= " ORDER BY completada ASC, fecha_actualizacion DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $notas = [];
    $mensaje = "Error al obtener notas: " . $e->getMessage();
    $tipo_mensaje = "error";
}

// ESTADÍSTICAS
try {
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(completada = 1) as completadas,
        SUM(completada = 0) as pendientes
    FROM notasjc");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = ['total' => 0, 'completadas' => 0, 'pendientes' => 0];
}

// CATEGORÍAS
try {
    $stmt = $pdo->query("SELECT DISTINCT categoria FROM notasjc ORDER BY categoria");
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categorias = [];
}

// Función tiempo relativo
function tiempo_relativo($fecha)
{
    $ahora = new DateTime();
    $fecha_obj = new DateTime($fecha);
    $diff = $ahora->diff($fecha_obj);

    if ($diff->d == 0 && $diff->h == 0) {
        return "hace " . $diff->i . " min";
    } elseif ($diff->d == 0) {
        return "hace " . $diff->h . " h";
    } elseif ($diff->d == 1) {
        return "hace 1 día";
    } else {
        return "hace " . $diff->d . " días";
    }
}
?>


<?php include __DIR__ . '/../templates/menu.php'; ?>


 
        
          
       


        <div class="row">
               <div class="w-100">
                <div class="card">
                  <div class="card-body">
                     <div class="header">
                        <h1>📝 Take Notes</h1>
<a href="https://github.com/jcduro" class="dash-btn" target="_blank">
    <span></span>
    <span></span>
    <span></span>
    <span></span>
    <i class="mdi mdi-github-circle" style="font-size: 28px;"></i>
  </a>

  <a href="/proyectos/dashjc/index.php" class="dash-btn">
    <span></span>
    <span></span>
    <span></span>
    <span></span>
    ← Volver al Dashboard
  </a>
</div>
        <?php if (!empty($mensaje)): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <!-- ESTADÍSTICAS -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                <div class="stat-label">Total de Notas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #00ff41;"><?php echo $stats['completadas'] ?? 0; ?></div>
                <div class="stat-label">Completadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #ffa500;"><?php echo $stats['pendientes'] ?? 0; ?></div>
                <div class="stat-label">Pendientes</div>
            </div>
        </div>

        <!-- CREAR NOTA -->
        <div class="form-section">
            <h2>✍️ Crear Nueva Nota</h2>
            <form method="POST">
                <input type="hidden" name="accion" value="crear">

                <div class="form-group">
                    <label for="nota">Nota *</label>
                    <textarea id="nota" name="nota" placeholder="Escribe tu nota aquí..." required></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="prioridad">Prioridad</label>
                        <select id="prioridad" name="prioridad">
                            <option value="baja">🟢 Baja</option>
                            <option value="media" selected>🟡 Media</option>
                            <option value="alta">🔴 Alta</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="categoria">Categoría</label>
                        <select id="categoria" name="categoria">
                            <option value="general">General</option>
                            <option value="trabajo">Trabajo</option>
                            <option value="personal">Personal</option>
                            <option value="ideas">Ideas</option>
                            <option value="bugs">Bugs</option>
                            <option value="aprendizaje">Aprendizaje</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="etiquetas">Etiquetas (separadas por comas)</label>
                        <input type="text" id="etiquetas" name="etiquetas" placeholder="ej: php, javascript, css">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">➕ Crear Nota</button>
            </form>
        </div>

        <!-- FILTROS -->
        <div class="filters-section">
            <h3>🔍 Filtros</h3>
            <form method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="form-group">
                        <label for="busqueda">Buscar</label>
                        <input type="text" id="busqueda" name="busqueda" placeholder="Buscar en notas..." value="<?php echo htmlspecialchars($busqueda); ?>">
                    </div>

                    <div class="form-group">
                        <label for="completada">Estado</label>
                        <select id="completada" name="completada" onchange="document.getElementById('filterForm').submit()">
                            <option value="todos" <?php echo $filtro_completada === 'todos' ? 'selected' : ''; ?>>Todas</option>
                            <option value="pendientes" <?php echo $filtro_completada === 'pendientes' ? 'selected' : ''; ?>>Pendientes</option>
                            <option value="completadas" <?php echo $filtro_completada === 'completadas' ? 'selected' : ''; ?>>Completadas</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="prioridad_filter">Prioridad</label>
                        <select id="prioridad_filter" name="prioridad" onchange="document.getElementById('filterForm').submit()">
                            <option value="todos" <?php echo $filtro_prioridad === 'todos' ? 'selected' : ''; ?>>Todas</option>
                            <option value="baja" <?php echo $filtro_prioridad === 'baja' ? 'selected' : ''; ?>>🟢 Baja</option>
                            <option value="media" <?php echo $filtro_prioridad === 'media' ? 'selected' : ''; ?>>🟡 Media</option>
                            <option value="alta" <?php echo $filtro_prioridad === 'alta' ? 'selected' : ''; ?>>🔴 Alta</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="categoria_filter">Categoría</label>
                        <select id="categoria_filter" name="categoria" onchange="document.getElementById('filterForm').submit()">
                            <option value="todos" <?php echo $filtro_categoria === 'todos' ? 'selected' : ''; ?>>Todas</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['categoria']; ?>" <?php echo $filtro_categoria === $cat['categoria'] ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($cat['categoria']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-top: 30px;">
                        <button type="submit" class="btn btn-primary">🔎 Buscar</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- NOTAS -->
        <div class="notes-container">
            <?php if (empty($notas)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>No hay notas</h3>
                    <p>Crea tu primera nota para comenzar</p>
                </div>
            <?php else: ?>
                <?php foreach ($notas as $nota): ?>
                    <div class="note-card <?php echo $nota['completada'] ? 'completada' : ''; ?>" id="nota-<?php echo $nota['cod_nota']; ?>">
                        <div class="note-header">
                            <span class="note-id">#<?php echo $nota['cod_nota']; ?></span>
                            <div class="note-meta">
                                <span class="badge badge-prioridad">
                                    <?php
                                    $emojis_prioridad = ['baja' => '🟢', 'media' => '🟡', 'alta' => '🔴'];
                                    echo $emojis_prioridad[$nota['prioridad']] . ' ' . ucfirst($nota['prioridad']);
                                    ?>
                                </span>
                                <span class="badge badge-categoria">
                                    📂 <?php echo ucfirst($nota['categoria']); ?>
                                </span>
                                <?php if (!empty($nota['etiquetas'])): ?>
                                    <span class="badge" style="background: rgba(255, 165, 0, 0.1); color: #ffa500;">
                                        🏷️ <?php echo htmlspecialchars($nota['etiquetas']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="note-text" id="texto-<?php echo $nota['cod_nota']; ?>">
                            <?php echo nl2br(htmlspecialchars($nota['nota'])); ?>
                        </div>

                        <div class="note-footer">
                            <div class="note-time">
                                ⏰ Actualizado <?php echo tiempo_relativo($nota['fecha_actualizacion']); ?>
                            </div>

                            <div class="note-actions">
                                <div class="checkbox-container">
                                    <input type="checkbox" id="check-<?php echo $nota['cod_nota']; ?>"
                                        <?php echo $nota['completada'] ? 'checked' : ''; ?>
                                        onchange="toggleCompletada(<?php echo $nota['cod_nota']; ?>)">
                                    <label for="check-<?php echo $nota['cod_nota']; ?>">Completada</label>
                                </div>

                                <button class="btn btn-edit" onclick="editarNota(<?php echo $nota['cod_nota']; ?>)">✏️ Editar</button>

                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar esta nota?');">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="cod_nota" value="<?php echo $nota['cod_nota']; ?>">
                                    <button type="submit" class="btn btn-danger">🗑️ Eliminar</button>
                                </form>
                            </div>
                        </div>

                        <!-- FORMULARIO EDICIÓN INLINE -->
                        <div class="edit-form" id="edit-form-<?php echo $nota['cod_nota']; ?>">
                            <form method="POST">
                                <input type="hidden" name="accion" value="actualizar">
                                <input type="hidden" name="cod_nota" value="<?php echo $nota['cod_nota']; ?>">

                                <div class="form-group">
                                    <label>Nota</label>
                                    <textarea name="nota" required><?php echo htmlspecialchars($nota['nota']); ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label>Nota</label>
                                    <textarea name="nota" required><?php echo htmlspecialchars($nota['nota']); ?></textarea>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Prioridad</label>
                                        <select name="prioridad">
                                            <option value="baja" <?php echo $nota['prioridad'] === 'baja' ? 'selected' : ''; ?>>🟢 Baja</option>
                                            <option value="media" <?php echo $nota['prioridad'] === 'media' ? 'selected' : ''; ?>>🟡 Media</option>
                                            <option value="alta" <?php echo $nota['prioridad'] === 'alta' ? 'selected' : ''; ?>>🔴 Alta</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label>Categoría</label>
                                        <select name="categoria">
                                            <option value="general" <?php echo $nota['categoria'] === 'general' ? 'selected' : ''; ?>>General</option>
                                            <option value="trabajo" <?php echo $nota['categoria'] === 'trabajo' ? 'selected' : ''; ?>>Trabajo</option>
                                            <option value="personal" <?php echo $nota['categoria'] === 'personal' ? 'selected' : ''; ?>>Personal</option>
                                            <option value="ideas" <?php echo $nota['categoria'] === 'ideas' ? 'selected' : ''; ?>>Ideas</option>
                                            <option value="bugs" <?php echo $nota['categoria'] === 'bugs' ? 'selected' : ''; ?>>Bugs</option>
                                            <option value="aprendizaje" <?php echo $nota['categoria'] === 'aprendizaje' ? 'selected' : ''; ?>>Aprendizaje</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label>Etiquetas</label>
                                        <input type="text" name="etiquetas" value="<?php echo htmlspecialchars($nota['etiquetas']); ?>" placeholder="separadas por comas">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <button type="submit" class="btn btn-save">✅ Guardar Cambios</button>
                                    <button type="button" class="btn btn-cancel" onclick="cancelarEdicion(<?php echo $nota['cod_nota']; ?>)">❌ Cancelar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        </div>
        </div>
        </div>
        </div>



    <script>
        function toggleCompletada(codNota) {
            const formData = new FormData();
            formData.append('accion', 'toggle_completada');
            formData.append('cod_nota', codNota);

            fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const noteCard = document.getElementById('nota-' + codNota);
                        noteCard.classList.toggle('completada');
                    } else {
                        alert('Error al actualizar la nota');
                        document.getElementById('check-' + codNota).checked = !document.getElementById('check-' + codNota).checked;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al actualizar');
                    document.getElementById('check-' + codNota).checked = !document.getElementById('check-' + codNota).checked;
                });
        }

        function editarNota(codNota) {
            const editForm = document.getElementById('edit-form-' + codNota);
            const noteText = document.getElementById('texto-' + codNota);

            if (editForm.classList.contains('active')) {
                cancelarEdicion(codNota);
            } else {
                editForm.classList.add('active');
                noteText.style.display = 'none';
            }
        }

        function cancelarEdicion(codNota) {
            const editForm = document.getElementById('edit-form-' + codNota);
            const noteText = document.getElementById('texto-' + codNota);

            editForm.classList.remove('active');
            noteText.style.display = 'block';
        }

        // Busqueda en tiempo real
        document.getElementById('busqueda').addEventListener('keyup', function() {
            const timeout = setTimeout(() => {
                document.getElementById('filterForm').submit();
            }, 500);
        });
    </script>

    </div>
