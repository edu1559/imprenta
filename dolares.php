<?php
// Dólares (medio "dolares"), ver sql/2026-11-26_dolares.sql.
// - El saldo se lleva en US$: lo contado en el último cierre (cierreMedios.usdUC) más los US$ de los
//   movimientos entre medios de la caja abierta (compra o venta de dólares; los US$ son obligatorios).
// - En pesos vale US$ × la cotización vigente (la última de cotizacionesDolar, cargada a mano: oficial,
//   promedio entre compra y venta).
// - Lo que cambia el valor en pesos por una cotización nueva es "diferencia por cotización": no es
//   faltante ni sobrante y no entra en la diferencia de la caja.
// - Los pedidos no se cobran ni se pagan en dólares (se bloquea en los pagos).
// Usa movimientosCajaAbierta() de auditoria.php.

const MENSAJE_PAGO_DOLARES = 'Los pedidos no se cobran ni se pagan en dólares: cargá el pago en pesos. Para comprar o vender dólares usá "Movimiento entre medios" en Cierre de Caja.';

function idMedioDolares($conn) {
    static $id = null;
    if ($id === null) {
        $fila = mysqli_fetch_row(mysqli_query($conn, "SELECT id FROM mediosPago WHERE medio = 'dolares'"));
        $id = $fila ? (int)$fila[0] : 0;
    }
    return $id;
}

function esMedioDolares($conn, $idMedio) {
    return idMedioDolares($conn) > 0 && (int)$idMedio === idMedioDolares($conn);
}

// Última cotización: ['id', 'fecha', 'compra', 'venta', 'valor', 'nota', 'usuario'] o null.
function cotizacionActual($conn) {
    $res = mysqli_query($conn, "SELECT c.*, u.usuario FROM cotizacionesDolar c LEFT JOIN usuarios u ON u.id = c.idUsuario
                                ORDER BY c.fecha DESC, c.id DESC LIMIT 1");
    return mysqli_fetch_assoc($res) ?: null;
}

// Estado de los dólares en la caja abierta:
//   usdUC, cotizacionUC, montoUC (pesos del último cierre), usdMov (US$ de los movimientos),
//   pesosMov (pesos de esos movimientos), usd (saldo en US$), cotizacion (vigente),
//   pesos (usd × cotización), difCotizacion (pesos − lo que daría la cuenta en pesos).
function estadoDolares($conn) {
    $idM = idMedioDolares($conn);
    $cm = mysqli_fetch_assoc(mysqli_query($conn, "SELECT fechaUC, montoUC, usdUC, cotizacionUC FROM cierreMedios WHERE idMedio = $idM"));
    if (!$cm) return null;
    $stmt = $conn->prepare("SELECT COALESCE(SUM(CASE WHEN idMedioDestino = ? THEN usd ELSE -usd END), 0)
                            FROM movimientosMedios
                            WHERE (idMedioOrigen = ? OR idMedioDestino = ?) AND fecha >= ?");
    $stmt->bind_param('iiis', $idM, $idM, $idM, $cm['fechaUC']);
    $stmt->execute();
    $usdMov = round((float)$stmt->get_result()->fetch_row()[0], 2);
    $pesosMov = (float)(movimientosCajaAbierta($conn)[$idM]['neto'] ?? 0);
    $cot = cotizacionActual($conn);
    $cotizacion = $cot ? (float)$cot['valor'] : (float)$cm['cotizacionUC'];
    $usd = round((float)$cm['usdUC'] + $usdMov, 2);
    $pesos = round($usd * $cotizacion, 2);
    return [
        'fechaUC' => $cm['fechaUC'], 'montoUC' => (float)$cm['montoUC'],
        'usdUC' => (float)$cm['usdUC'], 'cotizacionUC' => (float)$cm['cotizacionUC'],
        'usdMov' => $usdMov, 'pesosMov' => $pesosMov,
        'usd' => $usd, 'cotizacion' => $cotizacion, 'pesos' => $pesos,
        'difCotizacion' => round($pesos - ((float)$cm['montoUC'] + $pesosMov), 2),
        'cotizacionFila' => $cot,
    ];
}

function usdTexto($n) {
    return 'US$ ' . number_format((float)$n, 2, ',', '.');
}

function cotizacionTexto($n) {
    return '$' . number_format((float)$n, 2, ',', '.');
}
