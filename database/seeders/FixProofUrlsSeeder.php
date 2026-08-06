<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FixProofUrlsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $appUrl = env('APP_URL', 'http://localhost:8000');
        
        $this->command->info("Fixing URLs that start with {$appUrl}...");

        // 1. Fix ProofFile
        $updatedProofFiles = DB::table('proof_files')
            ->where('file_url', 'like', $appUrl . '/%')
            ->update([
                'file_url' => DB::raw("REPLACE(file_url, '{$appUrl}', '')")
            ]);
        $this->command->info("Updated {$updatedProofFiles} proof_files records.");

        // 2. Fix Trade
        $updatedTrades1 = DB::table('trades')
            ->where('buyer_payment_screenshot_url', 'like', $appUrl . '/%')
            ->update([
                'buyer_payment_screenshot_url' => DB::raw("REPLACE(buyer_payment_screenshot_url, '{$appUrl}', '')")
            ]);
        $updatedTrades2 = DB::table('trades')
            ->where('payment_screenshot_url', 'like', $appUrl . '/%')
            ->update([
                'payment_screenshot_url' => DB::raw("REPLACE(payment_screenshot_url, '{$appUrl}', '')")
            ]);
        $this->command->info("Updated " . ($updatedTrades1 + $updatedTrades2) . " trades records.");

        // 3. Fix Dispute (Buyer Proofs)
        $updatedDisputes1 = DB::table('disputes')
            ->where('buyer_screen_recording_url', 'like', $appUrl . '/%')
            ->update(['buyer_screen_recording_url' => DB::raw("REPLACE(buyer_screen_recording_url, '{$appUrl}', '')")]);
            
        $updatedDisputes2 = DB::table('disputes')
            ->where('buyer_bank_statement_url', 'like', $appUrl . '/%')
            ->update(['buyer_bank_statement_url' => DB::raw("REPLACE(buyer_bank_statement_url, '{$appUrl}', '')")]);
            
        $updatedDisputes3 = DB::table('disputes')
            ->where('buyer_upi_screenshot_url', 'like', $appUrl . '/%')
            ->update(['buyer_upi_screenshot_url' => DB::raw("REPLACE(buyer_upi_screenshot_url, '{$appUrl}', '')")]);
            
        $updatedDisputes4 = DB::table('disputes')
            ->where('buyer_screenshot_url', 'like', $appUrl . '/%')
            ->update(['buyer_screenshot_url' => DB::raw("REPLACE(buyer_screenshot_url, '{$appUrl}', '')")]);

        // 4. Fix Dispute (Seller Proofs)
        $updatedDisputes5 = DB::table('disputes')
            ->where('seller_screen_recording_url', 'like', $appUrl . '/%')
            ->update(['seller_screen_recording_url' => DB::raw("REPLACE(seller_screen_recording_url, '{$appUrl}', '')")]);
            
        $updatedDisputes6 = DB::table('disputes')
            ->where('seller_bank_statement_url', 'like', $appUrl . '/%')
            ->update(['seller_bank_statement_url' => DB::raw("REPLACE(seller_bank_statement_url, '{$appUrl}', '')")]);
            
        $updatedDisputes7 = DB::table('disputes')
            ->where('seller_txn_screenshot_url', 'like', $appUrl . '/%')
            ->update(['seller_txn_screenshot_url' => DB::raw("REPLACE(seller_txn_screenshot_url, '{$appUrl}', '')")]);

        $totalDisputesUpdated = $updatedDisputes1 + $updatedDisputes2 + $updatedDisputes3 + $updatedDisputes4 + $updatedDisputes5 + $updatedDisputes6 + $updatedDisputes7;
        
        $this->command->info("Updated {$totalDisputesUpdated} dispute URL fields.");
        
        $this->command->info("All URLs have been successfully updated to relative paths!");
    }
}
