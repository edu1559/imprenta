<?php  

require('../fpdf186/fpdf.php');
$pdf = new FPDF();
$pdf->AddPage('L');
$pdf->SetFont('Arial','B',20);
$pdf->Image('../imagenes/logoTrejo.jpeg', 10, 10, -300);
// $pdf->Cell(200,20,'Orden de trbajo',1,1,'C');


include_once('../conexion.php');
$conn = conectar();


if($_GET['idPedido']<>0){
    $id = $_GET['idPedido'];
 
    $sql = "select  trim(p.detalle),
                    trim(p.observaciones),
                    p.monto,
                    date_format(p.entrada,'%d/%c/%y %H hs'),
                    date_format(p.prometido,'%d/%c/%y %H hs'), 
                    c.apellido,
                    c.nombre ,
                    p.montopagado 
            from pedidos p inner join contactos c 
                    on p.idContacto = c.id 
            where p.id = $id";
    $result = mysqli_query($conn,$sql);
    $myrow = mysqli_fetch_row($result);

    $detalle = utf8_decode($myrow[0]);
    $observaciones = $myrow[1];
    $monto = $myrow[2];
    $entrada = $myrow[3];
    $prometido = $myrow[4];
    $apellido = $myrow[5];
    $nombre = $myrow[6];
    $montoPagado = $myrow[7];
};

/*
$result = mysqli_query($conn,$sql);

while ($myrow = mysqli_fetch_row ($result)){
   $pedidos[$myrow[0]]= array($myrow[0],$myrow[1],$myrow[2]);
};

*/
$pdf->SetY(35);
$pdf->Cell(250,20, utf8_decode('Orden de Trabajo Nº '.$id),1,1,'C');

$pdf->SetFont('Arial','B',12);
$pdf->Ln(20);	



$pdf->SetFillColor(177,225,247);
$pdf->SetFont('Arial','B',10);

$pdf->Cell(70,5,'fecha',1,0,'L',1);
$pdf->Cell(70,5,'Detalle',1,0,'L',1);
$pdf->Cell(30,5,'Monto',1,1,'L',1);






$pdf->SetFont('Arial','',10);
$pdf->Cell(70,5,$detalle,1,0,'L');
$pdf->Cell(70,5,$observaciones,1,0,'L');
$pdf->Cell(30,5,$monto,1,1,'L');




$pdf->SetXY(100,-35);
$pdf->SetFont('Arial','',9);

$pdf->Output();

?>