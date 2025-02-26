<?php
include "conexion.php";
require_once 'vendor/autoload.php'; 

if (isset($_GET['cliente_id'])) {
    $clienteId = $_GET['cliente_id'];

    $stmt = $conn->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->bind_param("i", $clienteId);
    $stmt->execute();
    $clienteResult = $stmt->get_result();
    $stmt->free_result();

    if ($clienteResult->num_rows === 0) {
        echo "Cliente no encontrado.";
        exit();
    }

    $cliente = $clienteResult->fetch_assoc();

    $stmt = $conn->prepare("SELECT * FROM ventas WHERE cliente_id = ? ORDER BY fecha DESC LIMIT 1");
    $stmt->bind_param("i", $clienteId);
    $stmt->execute();
    $ventaResult = $stmt->get_result();
    $stmt->free_result();

    if ($ventaResult->num_rows === 0) {
        echo "No se encontró ninguna venta para este cliente.";
        exit();
    }

    $venta = $ventaResult->fetch_assoc(); 
    $ventaId = $venta['id'];

    $stmt = $conn->prepare("SELECT * FROM venta_detalles WHERE venta_id = ?");
    $stmt->bind_param("i", $ventaId);
    $stmt->execute();
    $productosResult = $stmt->get_result();
    $stmt->free_result();

    if ($productosResult->num_rows === 0) {
        echo "No se encontraron productos para esta venta.";
        exit();
    }

    $pdf = new TCPDF();
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 12);

    $pdf->Cell(0, 10, "Factura ValorPoint", 0, 1, 'C');
    $pdf->Ln(10);

    $pdf->Cell(0, 10, "Emisor: ValorPoint", 0, 1);
    $pdf->Cell(0, 10, "RFC: XAXX010101000", 0, 1);
    $pdf->Cell(0, 10, "Regimen Fiscal: 601", 0, 1);
    $pdf->Ln(10);

    $pdf->Cell(0, 10, "Receptor: " . $cliente['nombre'], 0, 1);
    $pdf->Cell(0, 10, "RFC: " . $cliente['rfc'], 0, 1);
    $pdf->Ln(10);

    $pdf->Cell(40, 10, 'Cantidad', 0, 0);
    $pdf->Cell(80, 10, 'Producto', 0, 0);
    $pdf->Cell(40, 10, 'Precio Unitario', 0, 0);
    $pdf->Cell(40, 10, 'Total', 0, 1);

    $subtotal = 0;
    $totalVenta = 0;

    $xml = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml .= '<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/3" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sat.gob.mx/cfd/3 http://www.sat.gob.mx/sitio_internet/cfd/3/cfdv3.xsd" ';
    $xml .= 'Version="3.3" Serie="A" Folio="12345" Fecha="' . date('Y-m-d\TH:i:s') . '" Sello="" FormaPago="01" NoCertificado="00001000000403258791" Certificado="" SubTotal="';
    $xml .= number_format($subtotal, 2) . '" Moneda="MXN" Total="' . number_format($totalVenta, 2) . '" TipoDeComprobante="I">';

    $xml .= '<cfdi:Emisor Rfc="XAXX010101000" Nombre="Nombre de la Empresa" RegimenFiscal="601" />';
    $xml .= '<cfdi:Receptor Rfc="' . $cliente['rfc'] . '" Nombre="' . $cliente['nombre'] . '" DomicilioFiscalReceptor="Domicilio Fiscal" UsoCFDI="G03" />';
    $xml .= '<cfdi:Conceptos>';

    while ($producto = $productosResult->fetch_assoc()) {
        $productoTotal = $producto['cantidad'] * ($producto['total'] / $producto['cantidad']);
        $subtotal += $productoTotal;

        $xml .= '<cfdi:Concepto ClaveProdServ="01010101" NoIdentificacion="" Cantidad="' . $producto['cantidad'] . '" ClaveUnidad="MTR" Unidad="pieza" Descripcion="' . $producto['producto'] . '" ValorUnitario="' . number_format($producto['total'] / $producto['cantidad'], 2) . '" Importe="' . number_format($producto['total'], 2) . '" />';

        $pdf->Cell(40, 10, $producto['cantidad'], 0, 0);
        $pdf->Cell(80, 10, $producto['producto'], 0, 0);
        $pdf->Cell(40, 10, "$" . number_format($producto['total'] / $producto['cantidad'], 2), 0, 0);
        $pdf->Cell(40, 10, "$" . number_format($producto['total'], 2), 0, 1);

        $totalVenta += $producto['total'];
    }

    $xml .= '</cfdi:Conceptos>';

    $totalImpuestos = $subtotal * 0.16;
    $xml .= '<cfdi:Impuestos TotalImpuestosTrasladados="' . number_format($totalImpuestos, 2) . '">';
    $xml .= '<cfdi:Traslados>';
    $xml .= '<cfdi:Traslado Base="' . number_format($subtotal, 2) . '" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.16" Importe="' . number_format($totalImpuestos, 2) . '" />';
    $xml .= '</cfdi:Traslados>';
    $xml .= '</cfdi:Impuestos>';

    $xml .= '</cfdi:Comprobante>';

    $xmlFolderPath = 'factura_xml/';
    if (!is_dir($xmlFolderPath)) {
        mkdir($xmlFolderPath, 0777, true);  
    }

    $xmlFile = $xmlFolderPath . 'factura_' . $cliente['nombre'] . '.xml';
    file_put_contents($xmlFile, $xml);

    $pdf->Ln(10);  
    $pdf->Cell(120, 10, 'Subtotal:', 0, 0);
    $pdf->Cell(40, 10, "$" . number_format($subtotal, 2), 0, 1);
    
    $pdf->Cell(120, 10, 'IVA (16%):', 0, 0);
    $pdf->Cell(40, 10, "$" . number_format($totalImpuestos, 2), 0, 1);

    $pdf->Cell(120, 10, 'Total:', 0, 0);
    $pdf->Cell(40, 10, "$" . number_format($totalVenta + $totalImpuestos, 2), 0, 1);

    $pdf->Output('factura_' . $cliente['nombre'] . '.pdf', 'I');
} else {
    echo "No se recibió el ID del cliente.";
}
?>
