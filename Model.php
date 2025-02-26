<?php
include "conexion.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['nombreCliente'], $_POST['rfc'], $_POST['productos'], $_POST['cantidad'], $_POST['precio'])) {

        $nombreCliente = $_POST['nombreCliente'];
        $rfc = $_POST['rfc'];
        $productos = $_POST['productos'];
        $cantidades = $_POST['cantidad'];
        $precios = $_POST['precio'];

        $stmt = $conn->prepare("SELECT id FROM clientes WHERE rfc = ? OR nombre = ?");
        $stmt->bind_param("ss", $rfc, $nombreCliente);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $cliente = $result->fetch_assoc();
            $clienteId = $cliente['id']; 
        } else {
            $stmt = $conn->prepare("INSERT INTO clientes (nombre, rfc) VALUES (?, ?)");
            $stmt->bind_param("ss", $nombreCliente, $rfc);
            $stmt->execute();

            $clienteId = $stmt->insert_id;
        }

        $totales = [];
        $totalVenta = 0;

        foreach ($productos as $index => $producto) {
            $cantidad = isset($cantidades[$index]) ? $cantidades[$index] : 0;
            $precio = isset($precios[$index]) ? $precios[$index] : 0;
            $totalProducto = $precio * $cantidad;

            $totales[] = $totalProducto;
            $totalVenta += $totalProducto; 
        }

        $stmt = $conn->prepare("INSERT INTO ventas (cliente_id, fecha) VALUES (?, NOW())");
        $stmt->bind_param("i", $clienteId);  
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $ventaId = $stmt->insert_id;

            foreach ($productos as $index => $producto) {
                $cantidad = isset($cantidades[$index]) ? $cantidades[$index] : 0;
                $total = isset($totales[$index]) ? $totales[$index] : 0;

                $stmtDetalle = $conn->prepare("INSERT INTO venta_detalles (venta_id, producto, cantidad, total) VALUES (?, ?, ?, ?)");
                $stmtDetalle->bind_param("isid", $ventaId, $producto, $cantidad, $total);
                $stmtDetalle->execute();
            }

            header("Location: FacturarController.php?cliente_id=" . $clienteId);
            exit();
        } else {
            echo "Hubo un error al insertar la venta.";
        }
    } else {
        echo "Faltan datos en el formulario.";
    }
} else {
    echo "Método de solicitud no permitido.";
}
?>