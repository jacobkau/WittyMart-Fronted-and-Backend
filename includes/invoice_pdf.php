<?php
// Requires FPDF: composer require setasign/fpdf
require_once __DIR__ . '/../vendor/autoload.php';

function generateInvoicePDF($order, $items, $user) {
    $pdf = new FPDF();
    $pdf->AddPage();

    // Header
    $pdf->SetFont('Arial', 'B', 20);
    $pdf->SetTextColor(5, 87, 60);
    $pdf->Cell(0, 15, 'WittyMart', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 6, 'Smart Shopping for Witty Minds', 0, 1);
    $pdf->Ln(8);

    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 10, 'INVOICE', 0, 1);
    $pdf->Ln(4);

    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(40, 7, 'Order #:', 0, 0);
    $pdf->Cell(0, 7, $order['order_number'], 0, 1);
    $pdf->Cell(40, 7, 'Date:', 0, 0);
    $pdf->Cell(0, 7, date('M d, Y', strtotime($order['created_at'])), 0, 1);
    $pdf->Cell(40, 7, 'Status:', 0, 0);
    $pdf->Cell(0, 7, ucfirst($order['status']), 0, 1);
    $pdf->Cell(40, 7, 'Payment:', 0, 0);
    $pdf->Cell(0, 7, ucfirst(str_replace('_', ' ', $order['payment_method'] ?? '')), 0, 1);
    $pdf->Ln(6);

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'Bill To:', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, $order['delivery_recipient'] ?? ($user['name'] ?? ''), 0, 1);
    $pdf->Cell(0, 6, $order['delivery_phone'] ?? '', 0, 1);
    $pdf->MultiCell(0, 6, $order['shipping_address'] ?? '', 0, 1);
    $pdf->Ln(4);

    // Table header
    $pdf->SetFillColor(5, 87, 60);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(90, 8, 'Product', 1, 0, 'L', true);
    $pdf->Cell(20, 8, 'Qty', 1, 0, 'C', true);
    $pdf->Cell(35, 8, 'Price', 1, 0, 'R', true);
    $pdf->Cell(35, 8, 'Total', 1, 1, 'R', true);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 10);
    foreach ($items as $it) {
        $pdf->Cell(90, 8, substr($it['product_name'], 0, 45), 1, 0, 'L');
        $pdf->Cell(20, 8, $it['quantity'], 1, 0, 'C');
        $pdf->Cell(35, 8, 'Ksh ' . number_format($it['price'], 0), 1, 0, 'R');
        $pdf->Cell(35, 8, 'Ksh ' . number_format($it['total'], 0), 1, 1, 'R');
    }

    $pdf->Ln(4);
    $subtotal = $order['total'] - ($order['shipping_fee'] ?? 0);
    $pdf->Cell(145, 7, 'Subtotal:', 0, 0, 'R');
    $pdf->Cell(35, 7, 'Ksh ' . number_format($subtotal, 0), 0, 1, 'R');
    $pdf->Cell(145, 7, 'Shipping:', 0, 0, 'R');
    $pdf->Cell(35, 7, 'Ksh ' . number_format($order['shipping_fee'] ?? 0, 0), 0, 1, 'R');
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(145, 9, 'TOTAL:', 0, 0, 'R');
    $pdf->Cell(35, 9, 'Ksh ' . number_format($order['total'], 0), 0, 1, 'R');

    $pdf->SetY(-25);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell(0, 6, 'Thank you for shopping with WittyMart!', 0, 1, 'C');
    $pdf->Cell(0, 6, 'For support: support@wittymart.com', 0, 1, 'C');

    return $pdf->Output('S');
}
