<?php

  include_once('../conexion.php');
  $conn = conectar();

  if(isset($_GET['opcion'])) {
    $opcion = $_GET['opcion'];
  } else {
    $opcion = 'ninguna';
  };



  switch($opcion) {



      case 'mostrarPermisos':


            $idUsuario = $_GET['idUsuario'];

            // busco apellido,nombre y perfil.

            $sql = " select c.nombre,c.apellido, u.idPerfil
                     from usuarios u inner join contactos c
                            on u.id = c.id
                     where u.id = $idUsuario";

            $result = mysqli_query($conn,$sql);

            while ($myrow = mysqli_fetch_row($result)){
                $apellido = $myrow[0];
                $nombre = $myrow[1];
                $idPerfil = $myrow[2];
            };

            // Contenedor de todos los permisos

                echo "<div class=\"container bg-light p-3\">
                        <h4>Permisos de ". $apellido . " ". $nombre. "</h4>
                        <input type=\"hidden\" id=\"idUsuarioSeleccionado\" value=\"$idUsuario\"></div>";


            // array de padresCeros.
                    $sql = "select id,nombre
                            from menu
                            where padre is null";
                    $result = mysqli_query($conn,$sql);

                    $padreCero = array();



                    while($myrow = mysqli_fetch_row($result)){
                        $padreCero[$myrow[0]] = $myrow[1];
                    };

                    // muestro los permisos de cada menu


                    foreach ($padreCero as $clave => $valor){

                    echo "<div class=\"card m-3\" style=\"width:250px;display:flex;float:left\">";

                    echo "<table class=\"table table-striped;display-inline;flex-direction: row;justify-content:flex-start\" >

                          <tr>
                            <th class=\"table-success\" colspan=\"2\" style=\"text-align:center\">". $valor. "</th>
                          </tr>";

                                    $sql = "select m.id,m.nombre, coalesce(p.idMenu, 0) as valor
                                            from menu m left join permisos p
                                                on m.id = p.idMenu
                                                 and p.idUsuario=$idUsuario
                                            where padre=$clave";

                                    // echo $sql;



                    $result = mysqli_query($conn,$sql);

                    while ($mifila = mysqli_fetch_row($result)){

                        echo "<tr>
                                <td>" . $mifila[1]. "</td>
                                <td>
                                    <select class=\"selectPermiso\" id=\"". $mifila[0]. "\">
                                        <option value =\"0\">Ver</option>
                                        <option value =\"1\"";

                                        if($mifila[2]==0){
                                        echo " selected ";
                                        };

                                    echo ">No Ver</option>
                                    </select>
                                </td>
                            </tr>";
                        };
                        echo "</table>";
                        echo "</div>";

                        };



                        echo "</div>";


                        echo  "<script>";


                        echo "$('.selectPermiso').change(function(){

                                v_permiso = $(this).val();
                                v_idMenu = $(this).attr('id');
                                v_idUsuario = $('#idUsuarioSeleccionado').val();

                                v_url = 'administracion/ajaxPermisos.php?opcion=cambiarPermiso&permiso='+v_permiso +'&idMenu='+ v_idMenu + '&idUsuario=' + v_idUsuario;

                                alert(v_url);

                                $('#mensaje').load(v_url);
                            })";

                      echo  "</script>";

            break;


            case 'cambiarPermiso':

            $permiso = $_GET['permiso'];
            $idMenu = $_GET['idMenu'];
            $idUsuario = $_GET['idUsuario'];

            if ($permiso == 0){
                $sql = "insert into permisos (idMenu,idUsuario) values ($idMenu,$idUsuario)";

            }else{
                $sql = "delete from permisos where idMenu=$idMenu and idUsuario=$idUsuario;";
            };
            $result = mysqli_query($conn,$sql);
			if ($result){

            }

               };

            ?>






