<div class="container d-flex justify-content-center align-items-center" style="min-height: 70vh;">
    <div class="card border-0 shadow-lg p-4" style="width: 100%; max-width: 400px; border-radius: 15px;">
        <div class="text-center mb-4">
            <i class="bi bi-person-circle text-success display-2"></i>
            <h4 class="fw-bold mt-2">Acceso al Sistema</h4>
        </div>
        
        <div class="mb-3">
            <label class="form-label small fw-bold">Usuario</label>
            <input type="text" class="form-control form-control-lg bg-light" id="username" placeholder="Tu usuario">
        </div>
        <div class="mb-4">
            <label class="form-label small fw-bold">Contraseña</label>
            <input type="password" class="form-control form-control-lg bg-light" id="password" placeholder="••••••••">
        </div>

        <div class="d-grid gap-2">
            <button type="button" id="btnIniciarSession" class="btn btn-success btn-lg shadow-sm">
                <i class="bi bi-box-arrow-in-right me-2"></i>Ingresar
            </button>
            <button type="button" id="btnCerrarSession" class="btn btn-outline-danger btn-sm border-0">
                Cerrar Sesión Activa
            </button>
        </div>
        
        <div id="mensajes" class="mt-3 text-center"></div>
    </div>
</div>

<script>
   $('#btnIniciarSession').click(function(){
        let v_usuario = $('#username').val();
        let v_clave = $('#password').val();

        // Ponemos un spinner en el botón para que el usuario sepa que está cargando
        let btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Verificando...');

        $.get('ingreso/ajaxIngreso.php', {
            opcion: 'ingreso',
            usuario: v_usuario,
            clave: v_clave
        }, function(data) {
            $('#mensajes').html(data);
            
            // Si el mensaje contiene "Exitoso" (esto depende de lo que devuelva tu AJAX)
            if(data.includes('success') || data.includes('exitoso')) {
                setTimeout(() => location.href = 'index.php', 1000);
            } else {
                btn.prop('disabled', false).html('<i class="bi bi-box-arrow-in-right me-2"></i>Ingresar');
            }
        });
/*
        $.get('ingreso/ajaxIngreso.php', {
            opcion: 'ingreso',
            usuario: v_usuario,
            clave: v_clave
        }, function(data) {
            let respuesta = data.split('|');
            if(respuesta[0] == 'success') {
                // Todo OK, recargamos el index para que el menú cambie
                location.href = 'index.php'; 
            } else {
                // Error, mostramos el mensaje (respuesta[1])
                $('#mensajes').html('<div class="alert alert-danger">' + respuesta[1] + '</div>');
            }
        });
  */  
    });

    $('#btnCerrarSession').click(function(){
        $.get('ingreso/ajaxIngreso.php', { opcion: 'cerrarSesion' }, function() {
            location.reload();
        });
    });
</script>

