<?php
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
    $pdf->Ln(10);
    
    // Invoice title
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 10, 'INVOICE', 0, 1);
    $pdf->Ln(5);
    
    // Order details
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(40, 8, 'Order #:', 0, 0);
    $pdf->Cell(0, 8, $order['order_number'], 0, 1);
    $pdf->Cell(40, 8, 'Date:', 0, 0);
    $pdf->Cell(0, 8, date('M d, Y', strtotime($order['created_at'])), 0, 1);
    $pdf->Cell(40, 8, 'Status:', 0, 0);
    $pdf->Cell(0, 8, ucfirst($order['status']), 0, 1);
    $pdf->Ln(8);
    
    // Bill To
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'Bill To:', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, $order['delivery_recipient'] ?? $user['name'], 0, 1);
    $pdf->Cell(0, 6, $order['delivery_phone'] ?? '', 0, 1);
    $pdf->Cell(0, 6, $order['shipping_address'] ?? '', 0, 1);
    $pdf->Ln(8);
    
    // Items table header
    $pdf->SetFillColor(5, 87, 60);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(80, 8, 'Product', 1, 0, 'L', true);
    $pdf->Cell(25, 8, 'Qty', 1, 0, 'C', true);
    $pdf->Cell(35, 8, 'Price', 1, 0, 'R', true);
    $pdf->Cell(35, 8, 'Total', 1, 1, 'R', true);
    
    // Items
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 10);
    foreach ($items as $item) {
        $pdf->Cell(80, 8, substr($item['product_name'], 0, 40), 1, 0, 'L');
        $pdf->Cell(25, 8, $item['quantity'], 1, 0, 'C');
        $pdf->Cell(35, 8, 'Ksh ' . number_format($item['price'], 0), 1, 0, 'R');
        $pdf->Cell(35, 8, 'Ksh ' . number_format($item['total'], 0), 1, 1, 'R');
    }
    
    // Totals
    $pdf->Ln(4);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(140, 7, 'Subtotal:', 0, 0, 'R');
    $pdf->Cell(35, 7, 'Ksh ' . number_format($order['total'] - ($order['shipping_fee'] ?? 0), 0), 0, 1, 'R');
    $pdf->Cell(140, 7, 'Shipping:', 0, 0, 'R');
    $pdf->Cell(35, 7, 'Ksh ' . number_format($order['shipping_fee'] ?? 0, 0), 0, 1, 'R');
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(140, 9, 'TOTAL:', 0, 0, 'R');
    $pdf->Cell(35, 9, 'Ksh ' . number_format($order['total'], 0), 0, 1, 'R');
    
    // Footer
    $pdf->SetY(-30);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell(0, 6, 'Thank you for shopping with WittyMart!', 0, 1, 'C');
    $pdf->Cell(0, 6, 'For support: Wittyhighbrowtechnologies@gmail.com', 0, 1, 'C');
    
    return $pdf->Output('S'); // Return as string
}
