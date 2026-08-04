<?php

namespace App\Services;

class UpiDeepLinkService
{
    /**
     * Generate deep links for UPI payment applications
     */
    public function generateUpiDeepLinks(string $upiId, float $amount, string $tradeId): array
    {
        $encodedUpiId = urlencode($upiId);
        $encodedName = urlencode('Arr Wallet Seller');
        $encodedNote = urlencode("Arr Wallet Trade #{$tradeId}");
        $amountStr = number_format($amount, 2, '.', '');

        $baseUri = "upi://pay?pa={$encodedUpiId}&pn={$encodedName}&am={$amountStr}&tr={$tradeId}&tn={$encodedNote}&cu=INR";

        return [
            'generic' => $baseUri,
            'gpay'    => "gpay://upi/pay?pa={$encodedUpiId}&pn={$encodedName}&am={$amountStr}&tr={$tradeId}&tn={$encodedNote}&cu=INR",
            'phonepe' => "phonepe://pay?pa={$encodedUpiId}&pn={$encodedName}&am={$amountStr}&tr={$tradeId}&tn={$encodedNote}&cu=INR",
            'paytm'   => "paytmmp://pay?pa={$encodedUpiId}&pn={$encodedName}&am={$amountStr}&tr={$tradeId}&tn={$encodedNote}&cu=INR",
            'bhim'    => "in.org.npci.upiapp://pay?pa={$encodedUpiId}&pn={$encodedName}&am={$amountStr}&tr={$tradeId}&tn={$encodedNote}&cu=INR",
        ];
    }
}
