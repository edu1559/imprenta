<!-- Cuestiones sobre php -->


<div class="container-fluid " style="background-color:#eee">
		<img src="imagenes/cabecera_vacia.jpg" class="float-end" style="width:100%;height:150px;margin-top:0"> 
    

        



<div class="container mt-5">
  <div class="row">
    <div class="col-sm-4 p-5">
      <h3 style="text-align:center;width:100%;font-weight: bold">Nosotros</h3>
      <div class="container-fluid"><img src="imagenes/atencion.jpeg" class="float-end" style="width:100%"></div>
	  <div class="container-fluid float-end" style="width:100%;margin:2%">
		 <p>¿Dónde estamos? - Horario Atención<br>
			¿Quienes somos? - <br>
			Nuestra Historia</p>
	  </div>
	  <button type="button" class="btn btn-dark btn-block" style="width:100%" id="btnNosotros">Contáctenos</button>
    </div>
    <div class="col-sm-4 p-5">
      <h3 style="text-align:center;width:100%;font-weight: bold">Productos</h3>
      <div class="container-fluid"><img src="imagenes/productos.jpg" class="float-end" style="width:100%"></div>
	  <div class="container-fluid float-end" style="width:100%;margin:2%">
		 <p>Libros - Revistas - Apuntes - Folletos
			Tarjetas Personales - 
		   Formularios Comerciales - Libretas Escolares
		   Agendas - </p>
	  </div>
	  <button type="button" id="btnProductos" class="btn btn-primary btn-block" style="width:100%">Productos</button>
    </div>
    <div class="col-sm-4 p-5">
      <h3 style="text-align:center;width:100%;font-weight: bold">Servicios</h3>
      <div class="container-fluid"><img src="imagenes/servicios1.jpg" class="float-end" style="width:100%;"></div>
	  <div class="container-fluid float-end" style="width:100%;margin:2%">
		 <p>Impresiones - Fotografia - Apuntes - Folletos
			Tarjetas Personales - 
		   Formularios Comerciales - Libretas Escolares
		   Agendas - </p>
	  </div>
	  <button type="button" id="btnServicios"  class="btn btn-success btn-block" style="width:100%">Servicios</button>
    </div>
  </div>
</div>
</div>
<script>
	$('#btnNosotros').click(function(){
		$('#contenido').load('contactenos.php');		
	});
	$('#btnProductos').click(function(){
		$('#contenido').load('productos/productos.php');		
	});


</script>