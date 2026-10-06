<?php
include_once(__DIR__ . '/../sesion.php');
exigirTrabajadorAjax();

    include_once('../conexion.php');
	$conn = conectar();
    
    if(isset($_GET['opcion'])){
        $opcion = $_GET['opcion'];
    };


   if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['opcion'])) {

    $opcion = $_GET['opcion'];
  
    switch ($opcion){

    case 'buscaContacto':
            $search_term = isset($_GET['q']) ? $_GET['q'] : '';
                
            $sql = "SELECT id, CONCAT(apellido, ', ', nombre) as text
                    FROM contactos
                    WHERE apellido LIKE ? OR nombre LIKE ? LIMIT 20";

            $stmt = $conn->prepare($sql);
            $search_param = "%" . $search_term . "%";
            $stmt->bind_param("ss", $search_param, $search_param);
            $stmt->execute();
            $result = $stmt->get_result();

            $clientes = [];
            while ($row = $result->fetch_assoc()) {
                $clientes[] = ['id' => (string)$row['id'], 'text' => $row['text']];
            }
            
              
            // Envía la respuesta JSON
            echo json_encode(['results' => $clientes]);
                
            $stmt->close();
            $conn->close();

            break;

	case 'agregarContacto':
        // Alta desde Contactos > Agregar contacto. Devuelve JSON: ok + id, o el motivo del rechazo.
        $apellido = trim($_GET['apellido'] ?? '');
        $nombre   = trim($_GET['nombre'] ?? '');
        $telefono = trim($_GET['telefono'] ?? '');
        $correo   = trim($_GET['correo'] ?? '');
        $notas    = trim($_GET['notas'] ?? '');
        $esEmpresa = empty($_GET['esEmpresa']) ? 0 : 1;

        header('Content-Type: application/json');

        if ($apellido === '') {
            echo json_encode(['ok' => false, 'error' => 'El apellido o empresa es obligatorio.']);
            break;
        }

        // Duplicados: solo se compara el teléfono o el correo que se cargó (vacío no cuenta).
        $stmtDup = $conn->prepare("SELECT apellido, nombre FROM contactos
                                   WHERE (? <> '' AND telefono = ?) OR (? <> '' AND correo = ?) LIMIT 1");
        $stmtDup->bind_param("ssss", $telefono, $telefono, $correo, $correo);
        $stmtDup->execute();
        $dup = $stmtDup->get_result()->fetch_assoc();
        $stmtDup->close();
        if ($dup) {
            $quien = trim($dup['apellido'] . ', ' . $dup['nombre'], ', ');
            echo json_encode(['ok' => false, 'error' => "Ya existe un contacto con ese teléfono o correo: $quien. No se guardó."]);
            break;
        }

        $stmtIns = $conn->prepare("INSERT INTO contactos (apellido, nombre, telefono, correo, notas, esEmpresa, fechacarga)
                                   VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmtIns->bind_param("sssssi", $apellido, $nombre, $telefono, $correo, $notas, $esEmpresa);
        try {
            $stmtIns->execute();
            echo json_encode(['ok' => true, 'id' => $stmtIns->insert_id]);
        } catch (mysqli_sql_exception $e) {
            echo json_encode(['ok' => false, 'error' => 'No pudo cargarse: ' . $e->getMessage()]);
        }
        $stmtIns->close();

       break;

       case 'borraContacto':
        
            
            $id= $_GET['idContacto'];
            
            $sql = "delete from contactos 
                    where id=$id";
                    
     
            $result = mysqli_query($conn,$sql);
                if($result){
                    echo 'Se borró con éxito';    
                }else{
                     echo 'No pudo borrarse';
                }; 
        break;
    
     case 'actualizarContacto':	
		  	       
                    $id = $_GET['id'];
                    $apellido = urldecode($_GET['apellido']);
                    $nombre = urldecode($_GET['nombre']);
                    $telefono = $_GET['telefono'];
                    $correo = $_GET['correo'];
                    $notas = urldecode($_GET['notas']);
                    $esEmpresa = empty($_GET['esEmpresa']) ? 0 : 1;
                    
                $sql = "update contactos set 
                            apellido= '$apellido',
                            nombre = '$nombre',
                            telefono = '$telefono',
                            correo = '$correo',
                            notas = '$notas',
                            esEmpresa = $esEmpresa
                        where id = $id";
		//echo $sql;        
                $result = mysqli_query($conn,$sql);
                if ($result) {
                    echo "Se actualizo correctamene"; // Este mensaje será enviado al JavaScript
                } else {
                    echo "Error al actualizar contacto";
                };


       break;

    case 'agregarContactoInline':
        // Alta rápida de cliente desde el modal de Nuevo Pedido (pedidos/modalPedidoNuevo.php),
        // sin salir del formulario. Devuelve JSON con el id nuevo para poder seleccionarlo al toque.
        $apellido = isset($_GET['apellido']) ? trim(urldecode($_GET['apellido'])) : '';
        $nombre   = isset($_GET['nombre'])   ? trim(urldecode($_GET['nombre']))   : '';
        $telefono = isset($_GET['telefono']) ? trim($_GET['telefono']) : '';
        $correo   = isset($_GET['correo'])   ? trim($_GET['correo'])   : '';

        header('Content-Type: application/json');

        if ($apellido === '') {
            echo json_encode(['ok' => false, 'error' => 'El apellido es obligatorio']);
            break;
        }

        $stmtIns = $conn->prepare("INSERT INTO contactos (apellido, nombre, telefono, correo, fechacarga) VALUES (?, ?, ?, ?, NOW())");
        $stmtIns->bind_param("ssss", $apellido, $nombre, $telefono, $correo);

        if ($stmtIns->execute()) {
            $nuevoId = $stmtIns->insert_id;
            $texto = $apellido . ($nombre !== '' ? (', ' . $nombre) : '');
            echo json_encode(['ok' => true, 'id' => $nuevoId, 'text' => $texto]);
        } else {
            echo json_encode(['ok' => false, 'error' => $conn->error]);
        }
        $stmtIns->close();
    break;

    };
};
