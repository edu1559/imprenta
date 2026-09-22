<?php

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
		  	
                    $apellido = urldecode($_GET['apellido']);
                    $nombre = urldecode($_GET['nombre']);
                    $telefono = $_GET['telefono'];
                    $correo = $_GET['correo'];
                    $notas = urldecode($_GET['notas']);



            // Consulta para verificar duplicados por teléfono O correo
            $sql_check = "SELECT id 
                          FROM contactos 
                          WHERE telefono = '$telefono' 
                                OR correo = '$correo' 
                               LIMIT 1";

            // ¡IMPORTANTE! Asumo que tienes una conexión a la base de datos llamada $conn
            $result_check = mysqli_query($conn, $sql_check);

            if (mysqli_num_rows($result_check) > 0) {
                // Si se encuentra un registro, hay un duplicado.
                echo '<div class="alert alert-warning" role="alert">
                          <strong>Error:</strong> Ya existe un contacto con este Teléfono o Correo electrónico. No se ha guardado.
                      </div>';
                // Puedes salir del case aquí para no intentar la inserción
                break;
            }
                 
                $sql = "insert into contactos (apellido,nombre,telefono,correo,notas)
                        values ('$apellido','$nombre','$telefono','$correo','$notas')";
				echo $sql;
                $result = mysqli_query($conn,$sql);
		
                if($result){
                   echo 'Se cargó con éxito';    
                }else{
                    echo 'No pudo cargarse';
                }; 
			
    
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
                    
                $sql = "update contactos set 
                            apellido= '$apellido',
                            nombre = '$nombre',
                            telefono = '$telefono',
                            correo = '$correo',
                            notas = '$notas'  
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