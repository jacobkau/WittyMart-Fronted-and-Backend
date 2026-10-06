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
 * Generate a single-page, professionally-styled PDF invoice.
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
        $pdf->SetAutoPageBreak(false);  

        // ============================================
        // COLORS
        // ============================================
        $primary    = [5, 87, 60];
        $greyLight  = [245, 247, 250];
        $greyBorder = [225, 228, 232];
        $greyText   = [110, 115, 125];
        $darkText   = [34, 40, 49];

        // ============================================
        // HEADER BAND 
        // ============================================
        $pdf->SetFillColor($primary[0], $primary[1], $primary[2]);
        $pdf->Rect(0, 0, 210, 30, 'F');

        // ============================================
        // LOGO
        // ============================================
        $logoPath = __DIR__ . '/../images/wittymart-logo.png';
        if (file_exists($logoPath)) {
            // White circular backdrop behind the logo so it pops on the green band
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Circle(23, 15, 10.5, 'F');
            // Logo image on top of the backdrop
            $pdf->Image($logoPath, 13.5, 5.5, 19, 19);
        }

        // ============================================
        // BRAND NAME 
        // ============================================
        $pdf->SetFont('Arial', 'B', 20);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(38, 7);
        $pdf->Cell(90, 8, 'WittyMart', 0, 0, 'L');

        $pdf->SetFont('Arial', '', 8);
        $pdf->SetXY(38, 16);
        $pdf->Cell(90, 4, 'Smart Shopping for Witty Minds', 0, 0, 'L');

        // ============================================
        // "INVOICE" title on the right
        // ============================================
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->SetXY(120, 7);
        $pdf->Cell(75, 8, 'INVOICE', 0, 0, 'R');

        $pdf->SetFont('Arial', '', 8);
        $pdf->SetXY(120, 16);
        $pdf->Cell(75, 4, 'Order #' . $order['order_number'], 0, 0, 'R');

        // ============================================
        // FROM / BILL TO
        // ============================================
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor($greyText[0], $greyText[1], $greyText[2]);
        $pdf->SetXY(15, 38);
        $pdf->Cell(90, 4, 'FROM', 0, 0, 'L');

        $pdf->SetTextColor($darkText[0], $darkText[1], $darkText[2]);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetXY(15, 43);
        $pdf->Cell(90, 5, 'WittyMart', 0, 0, 'L');

        $pdf->SetFont('Arial', '', 8);
        $pdf->SetXY(15, 49);
        $pdf->Cell(90, 4, 'Nairobi, Kenya', 0, 0, 'L');
        $pdf->SetXY(15, 54);
        $pdf->Cell(90, 4, 'wittyhighbrowtechnologies@gmail.com', 0, 0, 'L');

        // BILL TO
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor($greyText[0], $greyText[1], $greyText[2]);
        $pdf->SetXY(120, 38);
        $pdf->Cell(75, 4, 'BILL TO', 0, 0, 'L');

        $pdf->SetTextColor($darkText[0], $darkText[1], $darkText[2]);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetXY(120, 43);
        $recipient = $order['delivery_recipient'] ?? ($user['name'] ?? 'Customer');
        $pdf->Cell(75, 5, $recipient, 0, 0, 'L');

        // Payment phone priority: mpesa_phone > delivery_phone > user.phone
        $paymentPhone = '';
        if (!empty($order['mpesa_phone']))        $paymentPhone = $order['mpesa_phone'];
        elseif (!empty($order['delivery_phone'])) $paymentPhone = $order['delivery_phone'];
        elseif (!empty($user['phone']))           $paymentPhone = $user['phone'];

        $pdf->SetFont('Arial', '', 8);
        if ($paymentPhone) {
            $pdf->SetXY(120, 49);
            $pdf->Cell(75, 4, 'Phone: ' . $paymentPhone, 0, 0, 'L');
        }
        if (!empty($user['email'])) {
            $pdf->SetXY(120, 54);
            $pdf->Cell(75, 4, $user['email'], 0, 0, 'L');
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
        $boxH = 14;
        $boxY = 64;
        $gap  = 2;

        foreach ($meta as $i => $m) {
            $x = 15 + ($i * ($boxW + $gap));

            $pdf->SetFillColor($greyLight[0], $greyLight[1], $greyLight[2]);
            $pdf->SetDrawColor($greyBorder[0], $greyBorder[1], $greyBorder[2]);
            $pdf->RoundedRect($x, $boxY, $boxW, $boxH, 2, 'DF');

            $pdf->SetFont('Arial', 'B', 6.5);
            $pdf->SetTextColor($greyText[0], $greyText[1], $greyText[2]);
            $pdf->SetXY($x + 3, $boxY + 2);
            $pdf->Cell($boxW - 6, 3.5, strtoupper($m[0]), 0, 0, 'L');

            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetTextColor($darkText[0], $darkText[1], $darkText[2]);
            $pdf->SetXY($x + 3, $boxY + 7);
            $pdf->Cell($boxW - 6, 5, $m[1], 0, 0, 'L');
        }

        // ============================================
        // DELIVERY ADDRESS (Y=82)
        // ============================================
        $y = 82;

        if (!empty($order['shipping_address']) || !empty($order['delivery_county'])) {
            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->SetTextColor($greyText[0], $greyText[1], $greyText[2]);
            $pdf->SetXY(15, $y);
            $pdf->Cell(180, 4, 'DELIVERY ADDRESS', 0, 0, 'L');

            $pdf->SetFont('Arial', '', 8.5);
            $pdf->SetTextColor($darkText[0], $darkText[1], $darkText[2]);

            $y += 5;
            if (!empty($order['shipping_address'])) {
                $pdf->SetXY(15, $y);
                $pdf->Cell(180, 4.5, $order['shipping_address'], 0, 0, 'L');
                $y += 4.5;
            }
            if (!empty($order['delivery_phone'])) {
                $pdf->SetXY(15, $y);
                $pdf->Cell(180, 4.5, 'Tel: ' . $order['delivery_phone'], 0, 0, 'L');
                $y += 4.5;
            }
            $y += 3;
        }

        // ============================================
        // ITEMS TABLE
        // ============================================
        $pdf->SetY($y);

        $pdf->SetFillColor($primary[0], $primary[1], $primary[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor($primary[0], $primary[1], $primary[2]);
        $pdf->SetFont('Arial', 'B', 8.5);

        $pdf->SetX(15);
        $pdf->Cell(95, 8, '  PRODUCT', 1, 0, 'L', true);
        $pdf->Cell(18, 8, 'QTY', 1, 0, 'C', true);
        $pdf->Cell(33, 8, 'UNIT PRICE', 1, 0, 'R', true);
        $pdf->Cell(34, 8, 'TOTAL  ', 1, 1, 'R', true);

        $pdf->SetFont('Arial', '', 8.5);
        $pdf->SetTextColor($darkText[0], $darkText[1], $darkText[2]);
        $pdf->SetDrawColor($greyBorder[0], $greyBorder[1], $greyBorder[2]);

      
        $maxRows = 10;
        $row = 0;
        foreach ($items as $it) {
            if ($row >= $maxRows) {
                $pdf->SetX(15);
                $pdf->SetFont('Arial', 'I', 8);
                $pdf->Cell(180, 7, '  ... and ' . (count($items) - $maxRows) . ' more item(s)', 'LR', 1, 'L', false);
                break;
            }

            $fill = ($row % 2 === 1);
            $pdf->SetFillColor($greyLight[0], $greyLight[1], $greyLight[2]);

            $pdf->SetX(15);
            $pdf->Cell(95, 7, '  ' . substr($it['product_name'], 0, 50), 1, 0, 'L', $fill);
            $pdf->Cell(18, 7, $it['quantity'], 'LR', 0, 'C', $fill);
            $pdf->Cell(33, 7, 'Ksh ' . number_format($it['price'], 0) . '  ', 'LR', 0, 'R', $fill);
            $pdf->Cell(34, 7, 'Ksh ' . number_format($it['total'], 0) . '  ', 'LR', 1, 'R', $fill);

            $row++;
        }

        $pdf->SetX(15);
        $pdf->Cell(180, 0, '', 'T', 1);

        // ============================================
        // TOTALS BLOCK
        // ============================================
        $pdf->Ln(3);

        $subtotal = $order['total'] - ($order['shipping_fee'] ?? 0);
        $shipping = $order['shipping_fee'] ?? 0;
        $discount = 0;
        if (isset($order['coupon_discount']) && $order['coupon_discount'] > 0) {
            $discount = (float)$order['coupon_discount'];
        }

        $totalsX = 120;

        // Subtotal
        $pdf->SetX($totalsX);
        $pdf->SetFont('Arial', '', 8.5);
        $pdf->SetTextColor($darkText[0], $darkText[1], $darkText[2]);
        $pdf->SetFillColor($greyLight[0], $greyLight[1], $greyLight[2]);
        $pdf->SetDrawColor($greyBorder[0], $greyBorder[1], $greyBorder[2]);
        $pdf->Cell(40, 6, '  Subtotal', 'LR', 0, 'L', false);
        $pdf->Cell(35, 6, 'Ksh ' . number_format($subtotal, 0) . '  ', 'LR', 1, 'R', false);

        // Discount
        if ($discount > 0) {
            $pdf->SetTextColor(40, 167, 69);
            $pdf->SetX($totalsX);
            $pdf->SetFont('Arial', '', 8.5);
            $pdf->SetFillColor($greyLight[0], $greyLight[1], $greyLight[2]);
            $pdf->SetDrawColor($greyBorder[0], $greyBorder[1], $greyBorder[2]);
            $pdf->Cell(40, 6, '  Discount', 'LR', 0, 'L', false);
            $pdf->Cell(35, 6, '-Ksh ' . number_format($discount, 0) . '  ', 'LR', 1, 'R', false);
        }

        // Shipping
        $pdf->SetTextColor($darkText[0], $darkText[1], $darkText[2]);
        $pdf->SetX($totalsX);
        $pdf->SetFont('Arial', '', 8.5);
        $pdf->SetFillColor($greyLight[0], $greyLight[1], $greyLight[2]);
        $pdf->SetDrawColor($greyBorder[0], $greyBorder[1], $greyBorder[2]);
        $pdf->Cell(40, 6, '  Shipping', 'LR', 0, 'L', false);
        $pdf->Cell(35, 6, 'Ksh ' . number_format($shipping, 0) . '  ', 'LR', 1, 'R', false);

        // Grand Total
        $pdf->SetX($totalsX);
        $pdf->SetFont('Arial', 'B', 10.5);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFillColor($primary[0], $primary[1], $primary[2]);
        $pdf->SetDrawColor($primary[0], $primary[1], $primary[2]);
        $pdf->Cell(40, 9, '  TOTAL', 1, 0, 'L', true);
        $pdf->Cell(35, 9, 'Ksh ' . number_format($order['total'], 0) . '  ', 1, 1, 'R', true);

        // ============================================
        // THANK YOU NOTICE
        // ============================================
        $pdf->Ln(6);

        $noticeY = $pdf->GetY();
        $pdf->SetFillColor($greyLight[0], $greyLight[1], $greyLight[2]);
        $pdf->SetDrawColor($greyBorder[0], $greyBorder[1], $greyBorder[2]);
        $pdf->RoundedRect(15, $noticeY, 180, 16, 2, 'DF');

        $pdf->SetFont('Arial', 'B', 8.5);
        $pdf->SetTextColor($primary[0], $primary[1], $primary[2]);
        $pdf->SetXY(20, $noticeY + 3);
        $pdf->Cell(170, 4.5, 'Thank you for shopping with WittyMart!', 0, 0, 'L');

        $pdf->SetFont('Arial', '', 7.5);
        $pdf->SetTextColor($greyText[0], $greyText[1], $greyText[2]);
        $pdf->SetXY(20, $noticeY + 8.5);
        $pdf->Cell(170, 4.5, 'For questions about this invoice, contact wittyhighbrowtechnologies@gmail.com', 0, 0, 'L');

        // ============================================
        // FOOTER
        // ============================================
        $pdf->SetY(-15);
        $pdf->SetDrawColor($greyBorder[0], $greyBorder[1], $greyBorder[2]);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());

        $pdf->SetY(-12);
        $pdf->SetFont('Arial', '', 7);
        $pdf->SetTextColor($greyText[0], $greyText[1], $greyText[2]);
        $pdf->SetX(15);
        $pdf->Cell(90, 5, 'WittyMart · Nairobi, Kenya', 0, 0, 'L');
        $pdf->Cell(90, 5, 'Page 1 of 1', 0, 0, 'R');

        return $pdf->Output('S');
    }
}
