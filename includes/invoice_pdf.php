<?php
// ============================================
// WITTYMART PDF INVOICE GENERATOR
// Requires FPDF: composer require setasign/fpdf
// ============================================
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Extend FPDF with RoundedRect for modern styling.
 */
if (!class_exists('WittyFPDF')) {
    class WittyFPDF extends FPDF
    {
        public function RoundedRect($x, $y, $w, $h, $r, $style = '')
        {
            $k = $this->k;
            $hp = $this->h;
            if ($style == 'F') $op = 'f';
            elseif ($style == 'FD' || $style == 'DF') $op = 'B';
            else $op = 'S';
            $MyArc = 4 / 3 * (sqrt(2) - 1);

            $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));

            $xc = $x + $w - $r;
            $yc = $y + $r;
            $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
            $this->_Arc($xc + $r * $MyArc, $yc - $r, $xc + $r, $yc - $r * $MyArc, $xc + $r, $yc);

            $xc = $x + $w - $r;
            $yc = $y + $h - $r;
            $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
            $this->_Arc($xc + $r, $yc + $r * $MyArc, $xc + $r * $MyArc, $yc + $r, $xc, $yc + $r);

            $xc = $x + $r;
            $yc = $y + $h - $r;
            $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
            $this->_Arc($xc - $r * $MyArc, $yc + $r, $xc - $r, $yc + $r * $MyArc, $xc - $r, $yc);

            $xc = $x + $r;
            $yc = $y + $r;
            $this->_out(sprintf('%.2F %.2F l', ($x) * $k, ($hp - $yc) * $k));
            $this->_Arc($xc - $r, $yc - $r * $MyArc, $xc - $r * $MyArc, $yc - $r, $xc, $yc);

            $this->_out($op);
        }

        private function _Arc($x1, $y1, $x2, $y2, $x3, $y3)
        {
            $h = $this->h;
            $this->_out(sprintf(
                '%.2F %.2F %.2F %.2F %.2F %.2F c',
                $x1 * $this->k,
                ($h - $y1) * $this->k,
                $x2 * $this->k,
                ($h - $y2) * $this->k,
                $x3 * $this->k,
                ($h - $y3) * $this->k
            ));
        }
    }
}

/**
 * Generate a professionally-styled PDF invoice.
 *
 * @param array $order  Order row from DB
 * @param array $items  Order items
 * @param array $user   User row (name, email, phone)
 * @return string       Raw PDF bytes
 */
if (!function_exists('generateInvoicePDF')) {
    function generateInvoicePDF($order, $items, $user)
    {
        $pdf = new WittyFPDF();
        $pdf->AddPage();

        // ============================================
        // COLORS
        // ============================================
        $primary     = [5, 87, 60];
        $greyLight   = [245, 247, 250];
        $greyBorder  = [225, 228, 232];
        $greyText    = [110, 115, 125];
        $darkText    = [34, 40, 49];

        // ============================================
        // HEADER BAND
        // ============================================
        $pdf->SetFillColor($primary[0], $primary[1], $primary[2]);
        $pdf->Rect(0, 0, 210, 32, 'F');

        $pdf->SetFont('Arial', 'B', 22);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(15, 8);
        $pdf->Cell(100, 10, 'WittyMart', 0, 0, 'L');

        $pdf->SetFont('Arial', '', 9);
        $pdf->SetXY(15, 18);
        $pdf->Cell(100, 5, 'Smart Shopping for Witty Minds', 0, 0, 'L');

        $pdf->SetFont('Arial', 'B', 18);
        $pdf->SetXY(120, 8);
        $pdf->Cell(75, 10, 'INVOICE', 0, 0, 'R');

        $pdf->SetFont('Arial', '', 9);
        $pdf->SetXY(120, 18);
        $pdf->Cell(75, 5, 'Order #' . $order['order_number'], 0, 0, 'R');

        // ============================================
        // FROM / BILL TO
        // ============================================
        $pdf->SetY(42);

        // FROM
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor($greyText[0], $greyText[1], $greyText[2]);
        $pdf->SetXY(15, 42);
        $pdf->Cell(90, 5, 'FROM', 0, 1, 'L');

        $pdf->SetTextColor($darkText[0], $darkText[1], $darkText[2]);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetX(15);
        $pdf->Cell(90, 6, 'WittyMart', 0, 1, 'L');

        $pdf->SetFont('Arial', '', 9);
        $pdf->SetX(15);
        $pdf->Cell(90, 5, 'Nairobi, Kenya', 0, 1, 'L');
        $pdf->SetX(15);
        $pdf->Cell(90, 5, 'support@wittymart.com', 0, 1, 'L');

        // BILL TO
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor($greyText[0], $greyText[1], $greyText[2]);
        $pdf->SetXY(120, 42);
        $pdf->Cell(75, 5, 'BILL TO', 0, 1, 'L');

        $pdf->SetTextColor($darkText[0], $darkText[1], $darkText[2]);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetXY(120, 47);
        $recipient = $order['delivery_recipient'] ?? ($user['name'] ?? 'Customer');
        $pdf->Cell(75, 6, $recipient, 0, 1, 'L');

        $paymentPhone = '';
        if (!empty($order['mpesa_phone'])) {
            $paymentPhone = $order['mpesa_phone'];
        } elseif (!empty($order['delivery_phone'])) {
            $paymentPhone = $order['delivery_phone'];
        } elseif (!empty($user['phone'])) {
            $paymentPhone = $user['phone'];
        }

        $pdf->SetFont('Arial', '', 9);
        if ($paymentPhone) {
            $pdf->SetXY(120, 53);
            $pdf->Cell(75, 5, 'Phone: ' . $paymentPhone, 0, 1, 'L');
        }
        if (!empty($user['email'])) {
            $pdf->SetXY(120, 58);
            $pdf->Cell(75, 5, $user['email'], 0, 1, 'L');
        }

        // ============================================
        // META GRID
        // ============================================
        $meta = [
            ['Invoice Date',   date('M d, Y', strtotime($order['created_at']))],
            ['Status',         ucfirst($order['status'])],
            ['Payment Method', ucfirst(str_replace('_', ' ', $order['payment_method'] ?? '—'))],
            ['Payment Status', ucfirst(str_replace('_', ' ', $order['payment_status'] ?? '—'))],
        ];

        $boxW = 43;
        $boxH = 16;
        $boxY = 78;
        $startX = 15;
        $gap = 2;

        foreach ($meta as $i => $m) {
            $x = $startX + ($i * ($boxW + $gap));

            $pdf->SetFillColor($greyLight[0], $greyLight[1], $greyLight[2]);
            $pdf->SetDrawColor($greyBorder[0], $greyBorder[1], $greyBorder[2]);
            $pdf->RoundedRect($x, $boxY, $boxW, $boxH, 2, 'DF');

            $pdf->SetFont('Arial', 'B', 7);
            $pdf->SetTextColor($greyText[0], $greyText[1], $greyText[2]);
            $pdf->SetXY($x + 3, $boxY + 3);
            $pdf->Cell($boxW - 6, 4, strtoupper($m[0]), 0, 0, 'L');

            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetTextColor($darkText[0], $darkText[1], $darkText[2]);
            $pdf->SetXY($x + 3, $boxY + 8);
            $pdf->Cell($boxW - 6, 6, $m[1], 0, 0, 'L');
        }

        // ============================================
        // DELIVERY ADDRESS
        // ============================================
        $pdf->SetY(100);

        if (!empty($order['shipping_address']) || !empty($order['delivery_county'])) {
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetTextColor($greyText[0], $greyText[1], $greyText[2]);
            $pdf->SetX(15);
            $pdf->Cell(180, 4, 'DELIVERY ADDRESS', 0, 1, 'L');

            $pdf->SetFont('Arial', '', 9);
            $pdf->SetTextColor($darkText[0], $darkText[1], $darkText[2]);

            $addressLines = [];
            if (!empty($order['shipping_address'])) $addressLines[] = $order['shipping_address'];
            if (!empty($order['delivery_phone']))    $addressLines[] = 'Tel: ' . $order['delivery_phone'];

            foreach ($addressLines as $line) {
                $pdf->SetX(15);
                $pdf->Cell(180, 5, $line, 0, 1, 'L');
            }

            $pdf->Ln(4);
        }

        // ============================================
        // ITEMS TABLE
        // ============================================
        $pdf->SetY(max($pdf->GetY(), 118));

        $pdf->SetFillColor($primary[0], $primary[1], $primary[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor($primary[0], $primary[1], $primary[2]);
        $pdf->SetFont('Arial', 'B', 9);

        $pdf->SetX(15);
        $pdf->Cell(95, 9, '  PRODUCT', 1, 0, 'L', true);
        $pdf->Cell(18, 9, 'QTY', 1, 0, 'C', true);
        $pdf->Cell(33, 9, 'UNIT PRICE', 1, 0, 'R', true);
        $pdf->Cell(34, 9, 'TOTAL  ', 1, 1, 'R', true);

        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor($darkText[0], $darkText[1], $darkText[2]);
        $pdf->SetDrawColor($greyBorder[0], $greyBorder[1], $greyBorder[2]);

        $row = 0;
        foreach ($items as $it) {
            $fill = ($row % 2 === 1);
            $pdf->SetFillColor($greyLight[0], $greyLight[1], $greyLight[2]);

            $pdf->SetX(15);
            $pdf->Cell(95, 8, '  ' . substr($it['product_name'], 0, 50), 1, 0, 'L', $fill);
            $pdf->Cell(18, 8, $it['quantity'], 'LR', 0, 'C', $fill);
            $pdf->Cell(33, 8, 'Ksh ' . number_format($it['price'], 0) . '  ', 'LR', 0, 'R', $fill);
            $pdf->Cell(34, 8, 'Ksh ' . number_format($it['total'], 0) . '  ', 'LR', 1, 'R', $fill);

            $row++;
        }

        $pdf->SetX(15);
        $pdf->Cell(180, 0, '', 'T', 1);

        // ============================================
        // TOTALS BLOCK
        // ============================================
        $pdf->Ln(6);

        $subtotal = $order['total'] - ($order['shipping_fee'] ?? 0);
        $shipping = $order['shipping_fee'] ?? 0;
        $discount = 0;
        if (isset($order['coupon_discount']) && $order['coupon_discount'] > 0) {
            $discount = (float)$order['coupon_discount'];
        }

        $totalsX = 120;
        $totalsW = 75;

        // Subtotal
        $pdf->SetX($totalsX);
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor($darkText[0], $darkText[1], $darkText[2]);
        $pdf->SetFillColor($greyLight[0], $greyLight[1], $greyLight[2]);
        $pdf->SetDrawColor($greyBorder[0], $greyBorder[1], $greyBorder[2]);
        $pdf->Cell(40, 7, '  Subtotal', 'LR', 0, 'L', false);
        $pdf->Cell(35, 7, 'Ksh ' . number_format($subtotal, 0) . '  ', 'LR', 1, 'R', false);

        // Discount
        if ($discount > 0) {
            $pdf->SetTextColor(40, 167, 69);
            $pdf->SetX($totalsX);
            $pdf->SetFont('Arial', '', 9);
            $pdf->SetFillColor($greyLight[0], $greyLight[1], $greyLight[2]);
            $pdf->SetDrawColor($greyBorder[0], $greyBorder[1], $greyBorder[2]);
            $pdf->Cell(40, 7, '  Discount', 'LR', 0, 'L', false);
            $pdf->Cell(35, 7, '-Ksh ' . number_format($discount, 0) . '  ', 'LR', 1, 'R', false);
        }

        // Shipping
        $pdf->SetTextColor($darkText[0], $darkText[1], $darkText[2]);
        $pdf->SetX($totalsX);
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetFillColor($greyLight[0], $greyLight[1], $greyLight[2]);
        $pdf->SetDrawColor($greyBorder[0], $greyBorder[1], $greyBorder[2]);
        $pdf->Cell(40, 7, '  Shipping', 'LR', 0, 'L', false);
        $pdf->Cell(35, 7, 'Ksh ' . number_format($shipping, 0) . '  ', 'LR', 1, 'R', false);

        // Grand Total
        $pdf->SetX($totalsX);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFillColor($primary[0], $primary[1], $primary[2]);
        $pdf->SetDrawColor($primary[0], $primary[1], $primary[2]);
        $pdf->Cell(40, 10, '  TOTAL', 1, 0, 'L', true);
        $pdf->Cell(35, 10, 'Ksh ' . number_format($order['total'], 0) . '  ', 1, 1, 'R', true);

        // ============================================
        // THANK YOU NOTICE
        // ============================================
        $pdf->Ln(10);

        $noticeY = $pdf->GetY();
        $pdf->SetFillColor($greyLight[0], $greyLight[1], $greyLight[2]);
        $pdf->SetDrawColor($greyBorder[0], $greyBorder[1], $greyBorder[2]);
        $pdf->RoundedRect(15, $noticeY, 180, 20, 2, 'DF');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor($primary[0], $primary[1], $primary[2]);
        $pdf->SetXY(20, $noticeY + 4);
        $pdf->Cell(170, 5, 'Thank you for shopping with WittyMart!', 0, 0, 'L');

        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor($greyText[0], $greyText[1], $greyText[2]);
        $pdf->SetXY(20, $noticeY + 10);
        $pdf->Cell(170, 5, 'For questions about this invoice, contact support@wittymart.com or call +254 768 374 497.', 0, 0, 'L');

        // ============================================
        // FOOTER
        // ============================================
        $pdf->SetY(-18);
        $pdf->SetDrawColor($greyBorder[0], $greyBorder[1], $greyBorder[2]);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());

        $pdf->SetY(-15);
        $pdf->SetFont('Arial', '', 7);
        $pdf->SetTextColor($greyText[0], $greyText[1], $greyText[2]);
        $pdf->SetX(15);
        $pdf->Cell(90, 5, 'WittyMart · Nairobi, Kenya', 0, 0, 'L');
        $pdf->Cell(90, 5, 'Page 1 of 1', 0, 0, 'R');

        return $pdf->Output('S');
    }
}
