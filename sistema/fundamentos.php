<!--Creación de Menú con boostrap 5 -->
<style>
dd,dt{
	font-size:1.2em;
}

</style>


<div class=" container-fluid  my-3 p-5">
	<h3>Creación de Menú en Boostrap 5</h3>
	

	
	<div class="container-fluid p-5 my-5 bg-white">
	
	<dl class="text-black">
            <dt>Nav simples:<br></dt>
            <dd>- Para las estructuras de menú  se utiliza las clase .nav y las etiquetas div, ul y li</dd>
            <dd>- la clase .nav  o navbar-nav se pone en la etiqueta ul</dd>
            <dd>- la clase .navbar-nav se pone en la etiqueta li</dd>
            <dd>- Cada li tiene lleva una clase .nav-item</dd>
            <dd>- Dentro del li va generalmente un anchor con su href con la clase .nav-link</dd>
            <dd>- Para desahabiltar un item se utiliza el atributo "disabled"</dd>
            
	</dl>
	

</div>


 <h3 class="text-center">Menú simple</h3>

		<div class="container-fluid">

    <div class="row bg-white text-black bordered">
        <div class="col-sm-6 my-5 p-5 bg-warning">
	<xmp>
 	 
	 <div class="container mt-3"> 
	  <ul class="nav">
		<li class="nav-item">
		  <a class="nav-link" href="#">Renault</a>
		</li>
		<li class="nav-item">
		  <a class="nav-link" href="#">Fiat</a>
		</li>
		<li class="nav-item">
		  <a class="nav-link" href="#">Ford</a>
		</li>
		<li class="nav-item">
		  <a class="nav-link disabled" href="#">Audi</a>
		</li>
	  </ul>
	</div>

	</xmp>
            
</div>

    
<div class="col-sm-6 my-5 p-5 bg-light">
    
    <div class="container mt-3">
    <h2>Menú en su forma más simple</h2>
    <p>Categorías de autos:</p>
    <ul class="nav">
        <li class="nav-item">
        <a class="nav-link" href="#">Renault</a>
        </li>
        <li class="nav-item">
        <a class="nav-link" href="#">Fiat</a>
        </li>
        <li class="nav-item">
        <a class="nav-link" href="#">Ford</a>
        </li>
        <li class="nav-item">
        <a class="nav-link disabled" href="#">Audi</a>
        </li>
    </ul>
    </div>
    
</div>
    
</div>



 <h3 class="text-center p-5">Alineado de los nav</h3>

<div class="container-fluid bg-white p-5 my-3">

  

    <dl class="text-black">
            <dt>Alineación:</dt>
            <dd>- Por defecto la estructura se alinea a la izquierda</dd>
            <dd>- Para colocar el menú en el centro utilice en la etiqueta ul la clase .justify-content-center</dd>
            <dd>- Para colocar el menú a la derecha utilice en la etiqueta ul la clase .justify-content-end</dd>
           
            
	</dl>    
        
<!-- nav a la izquierda-->
    <h5>Nav a la izquierda</h5>
    <ul class="nav">
        <li class="nav-item">
        <a class="nav-link" href="#">Renault</a>
        </li>
        <li class="nav-item">
        <a class="nav-link" href="#">Fiat</a>
        </li>
        <li class="nav-item">
        <a class="nav-link" href="#">Ford</a>
        </li>
        <li class="nav-item">
        <a class="nav-link disabled" href="#">Audi</a>
        </li>
    </ul>            
    
    
    <h5 class="text-center">Nav al centro</h5>
    <ul class="nav justify-content-center">
        <li class="nav-item">
        <a class="nav-link" href="#">Renault</a>
        </li>
        <li class="nav-item">
        <a class="nav-link" href="#">Fiat</a>
        </li>
        <li class="nav-item">
        <a class="nav-link" href="#">Ford</a>
        </li>
        <li class="nav-item">
        <a class="nav-link disabled" href="#">Audi</a>
        </li>
    </ul>           
    
    
    <h5 class="text-right">Nav derecho</h5>
    <ul class="nav justify-content-end">
        <li class="nav-item">
        <a class="nav-link" href="#">Renault</a>
        </li>
        <li class="nav-item">
        <a class="nav-link" href="#">Fiat</a>
        </li>
        <li class="nav-item">
        <a class="nav-link" href="#">Ford</a>
        </li>
        <li class="nav-item">
        <a class="nav-link disabled" href="#">Audi</a>
        </li>
    </ul>     
    </div>

    
    
    
</div>  


<h3 class="text-center p-5">Nav Vertical</h3>

<div class="container-fluid bg-white p-5 my-3">

  

    <dl class="text-black">
            <dt>Hacer un menú vertical:</dt>
            <dd>- Se agrega a la clase .nav de ul la clase .flex-column</dd>
        
           
            
	</dl>    
        
<!-- nav a la izquierda-->
    <h5>Nav vertical</h5>
    <ul class="nav flex-column">
        <li class="nav-item">
        <a class="nav-link" href="#">Renault</a>
        </li>
        <li class="nav-item">
        <a class="nav-link" href="#">Fiat</a>
        </li>
        <li class="nav-item">
        <a class="nav-link" href="#">Ford</a>
        </li>
        <li class="nav-item">
        <a class="nav-link disabled" href="#">Audi</a>
        </li>
    </ul>            
    
 

    
    
    
</div>  

<h3 class="text-center p-5">Tabs</h3>

<div class="container-fluid bg-white p-5 my-3">

  

    <dl class="text-black">
            <dt>la clase .nav-tab:</dt>
            
            <dd>- la única diferencia es que se utiliza además la clase .nav-tabs </dd>
           
            
	</dl>    
        
<!-- nav a la izquierda-->
    <h5>Nav Tabs</h5>
    <ul class="nav nav-tabs">
        <li class="nav-item">
        <a class="nav-link" href="#">Renault</a>
        </li>
        <li class="nav-item">
        <a class="nav-link" href="#">Fiat</a>
        </li>
        <li class="nav-item">
        <a class="nav-link" href="#">Ford</a>
        </li>
        <li class="nav-item">
        <a class="nav-link disabled" href="#">Audi</a>
        </li>
    </ul>            
    
 

    
    
    
</div> 


<h3 class="text-center p-5">Toggeabled Tabs</h3>

<div class="container-fluid bg-white p-5 my-3">

  

    <dl class="text-black">
            <dt>la clase .nav-tab:</dt>
            <dd>- Los tab, son menu en forma de ficheros que permiten mostrar varias items del mismo </dd>
            <dd>- se utiliza además la clase .nav-tabs y se agrga un atributo role="tablist"</dd>
            <dd>- Se utiliza una estructura de div para contenidos con la clase .tab-content</dd>
            <dd>- Cada item de la estructura de contenidos tiene un div con el id que se referencia  desde el nav</dd>
            <dd>- Cada item también lleva la clases: .container y la clase .tab-pane las clases .fade y .active permiten mostrar u ocultar </dd>
            
	</dl>    
      

 
  <ul class="nav nav-tabs" role="tablist">
    <li class="nav-item">
      <a class="nav-link active" data-bs-toggle="tab" href="#renault">Renault</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#fiat">Fiat</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#ford">Ford</a>
    </li>
  </ul>
</div>
  <!-- Tab panes -->
  <div class="tab-content">
    <div id="renault" class="container tab-pane active  text-black"><br>
      <h3>RENAULT</h3>
      <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
    </div>
    <div id="fiat" class="container tab-pane fade text-black "><br>
      <h3>FIAT</h3>
      <p>Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
    </div>
    <div id="ford" class="container tab-pane fade  text-black "><br>
      <h3>FORD</h3>
      <p>Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam.</p>
    </div>
  </div>
       
    
 

    
    
    



<h3 class="text-center p-5">Barras de Navegación</h3>

<div class="container-fluid bg-white p-5 my-3">


    <dl class="text-black">
            <dt>las clases .nav y .navbar:</dt>
            <dd>-Las clases .nav seguida de .navbar-expand-xxl|xl|lg|md|sm permiten que una barra de navegación colapse o se extienda </dd>
            <dd>-Las clases .navbar-dark sirve para poner claro los textos de los link en una barra oscurecida con la clase .bg-dark</dd>
            
            
   </dl>    
        
<!-- barra de menu-->
    <!-- Nav tabs -->
 <div class="container bg-secondary"> 
    <xmp>
    <nav class="navbar navbar-expand-sm bg-dark navbar-dark ">
    <div class="container-fluid">
        <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" href="#">Link 1</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#">Link 2</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#">Link 3</a>
        </li>
        </ul>
    </div>
    </nav>
    </xmp>
</div>
  
  
    
 <nav class="navbar navbar-expand-sm bg-dark navbar-dark">
  <div class="container-fluid">
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="navbar-brand" href="paris.jpg">Logo</a>
     </li>
      <li class="nav-item">
        <a class="nav-link" href="#">Link 1</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="#">Link 2</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="#">Link 3</a>
      </li>
    </ul>
     <form class="d-flex">
        <input class="form-control me-2" type="text" placeholder="Buscar en la página">
        <button class="btn btn-primary" type="button">Buscar</button>
      </form>
  </div>
</nav>

    

    
</div>







<h3 class="text-center p-5">Código generado por Bing con explicación</h3>

<div class="container-fluid bg-white p-5 my-3">


    <dl class="text-black">
            <dt>las clases utilizadas en este ejemplo</dt>
            <dd>-Las clases .nav seguida de .navbar-expand-xxl|xl|lg|md|sm permiten que una barra de navegación colapse o se extienda </dd>
            <dd>-Las clases .navbar-dark sirve para poner claro los textos de los link en una barra oscurecida con la clase .bg-dark</dd>
          
            <dd>-navbar-brand: aplica un estilo especial al elemento que representa la marca o el nombre del sitio web. </dd>
            <dd>- navbar-toggler: crea un botón que se muestra cuando la barra de navegación está colapsada y que permite alternar su visibilidad al hacer clic en él.</dd>
            
               <dd>-navbar-toggler-icon: crea un icono de tres líneas horizontales para el botón anterior. </dd>
            <dd>- data-bs-toggle, data-bs-target, aria-controls, aria-expanded, aria-label: son atributos personalizados que permiten activar el comportamiento del menú desplegable con JavaScript y mejorar la accesibilidad del mismo.</dd>
    </dl> 
    
   
   <xmp>
    collapse: oculta el contenido del elemento al que se aplica hasta que se active su expansión.
    navbar-collapse: aplica un estilo específico al contenido colapsable de la barra de navegación.
    navbar-nav: crea una lista de elementos de navegación con un estilo horizontal.
    me-auto: aplica un margen automático a la derecha del elemento al que se aplica, lo que hace que se alinee a la izquierda.
    mb-2, mb-lg-0: aplican un margen inferior de 2 unidades al elemento al que se aplican en pantallas pequeñas y medianas, y ningún margen en pantallas grandes.
    nav-item: crea un elemento de navegación individual con un estilo predeterminado.
    nav-link: crea un enlace dentro de un elemento de navegación con un estilo predeterminado.
    active: aplica un estilo diferente al elemento al que se aplica para indicar que está activo o seleccionado.
    aria-current: es un atributo que indica el elemento actual dentro de una estructura de navegación.
    dropdown: crea un elemento de navegación que contiene un menú desplegable con otras opciones.
    dropdown-toggle: crea un enlace que activa el menú desplegable al hacer clic en él y le agrega un icono de flecha hacia abajo.
    id, role, aria-expanded: son atributos que identifican el elemento, le asignan un rol semántico y le indican su estado de expansión, respectivamente.
    dropdown-menu: crea un menú desplegable con un estilo predeterminado que se muestra debajo del elemento que lo activa.
    aria-labelledby: es un atributo que asocia el menú desplegable con el elemento que lo activa mediante su id.
    dropdown-item: crea un elemento individual dentro del menú desplegable con un estilo predeterminado.
    dropdown-divider: crea una línea horizontal que separa los elementos del menú desplegable.

</xmp>
            
            
      
        
<!-- barra de menu-->
    <!-- Nav tabs -->
 <div class="container bg-secondary"> 
    <xmp>
    <nav class="navbar navbar-expand-lg navbar-light bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Mi sitio web</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link active" aria-current="page" href="#">Inicio</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#">Acerca de</a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            Servicios
          </a>
          <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
            <li><a class="dropdown-item" href="#">Diseño web</a></li>
            <li><a class="dropdown-item" href="#">Desarrollo web</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="#">Marketing digital</a></li>
          </ul>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#">Contacto</a>
        </li>
      </ul>
    </div>
  </div>
</nav>


    </xmp>
</div>
  
  
    
 <nav class="navbar navbar-expand-lg navbar-light bg-primary">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Mi sitio web</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link active" aria-current="page" href="#">Inicio</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#">Acerca de</a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            Servicios
          </a>
          <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
            <li><a class="dropdown-item" href="#">Diseño web</a></li>
            <li><a class="dropdown-item" href="#">Desarrollo web</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="#">Marketing digital</a></li>
          </ul>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#">Contacto</a>
        </li>
      </ul>
    </div>
  </div>
</nav>


    

    
</div>
</div>
    
</div>
