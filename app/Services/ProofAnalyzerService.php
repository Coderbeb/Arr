<?php

namespace App\Services;

use App\Models\FraudHash;
use App\Models\ProofFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ProofAnalyzerService
{
    /**
     * Store proof file, compute SHA-256 hash, and check anti-fraud registry
     */
    public function storeAndHashProof(UploadedFile $file, string $userId, ?string $tradeId = null, ?string $disputeId = null, string $category = 'proof'): array
    {
        $hash = hash_file('sha256', $file->getRealPath());

        // Check if hash already flagged in fraud registry
        $fraudMatch = FraudHash::where('file_hash', $hash)->first();

        // Save file to storage
        $path = $file->store("proofs/{$category}", 'public');
        $url = Storage::disk('public')->url($path);

        $proofFile = ProofFile::create([
            'id' => (string) Str::uuid(),
            'dispute_id' => $disputeId,
            'trade_id' => $tradeId,
            'uploaded_by' => $userId,
            'file_type' => $category,
            'file_url' => $url,
            'file_hash' => $hash,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        return [
            'proof_file' => $proofFile,
            'url' => $url,
            'hash' => $hash,
            'is_reused' => $fraudMatch !== null,
        ];
    }

    public function analyzeProof(string $hash, string $mimeType, int $size, string $userId): array
    {
        $score = 85;
        $breakdown = [];
        
        // Find the file path from ProofFile
        $proofFile = \App\Models\ProofFile::where('file_hash', $hash)->first();
        if (!$proofFile) {
            return ['score' => 50, 'breakdown' => ['error' => 'File not found for analysis'], 'is_duplicate' => false];
        }

        $filePath = storage_path('app/public/proofs/' . $proofFile->file_type . '/' . basename($proofFile->file_url));
        
        $getID3 = new \getID3();
        $fileInfo = @$getID3->analyze($filePath);

        if (str_starts_with($mimeType, 'image/')) {
            $breakdown = [
                'file_authenticity' => 90,
                'metadata_validity' => 85,
                'photoshop_tampering_risk' => 0,
                'ai_generated_probability' => 0,
                'visual_consistency' => 95,
            ];

            if (isset($fileInfo['error'])) {
                $breakdown['photoshop_tampering_risk'] = 30; // Suspicious if cannot parse
            } else {
                $software = strtolower($fileInfo['jpg']['exif']['IFD0']['Software'] ?? $fileInfo['png']['text']['Software'] ?? '');
                $creator = strtolower($fileInfo['jpg']['exif']['IFD0']['Creator'] ?? '');
                
                $suspiciousKeywords = ['photoshop', 'canva', 'picsart', 'snapseed', 'lightroom', 'illustrator', 'coreldraw'];
                
                $isEdited = false;
                foreach ($suspiciousKeywords as $kw) {
                    if (str_contains($software, $kw) || str_contains($creator, $kw)) {
                        $isEdited = true;
                        break;
                    }
                }

                if ($isEdited) {
                    $breakdown['photoshop_tampering_risk'] = 95;
                    $breakdown['file_authenticity'] = 10;
                    $breakdown['fraud_flag'] = 'Editing software signature detected: ' . $software;
                }
            }
            $score = 100 - $breakdown['photoshop_tampering_risk'] - $breakdown['ai_generated_probability'];
        } elseif (str_starts_with($mimeType, 'video/')) {
            $breakdown = [
                'screen_recording_authenticity' => 85,
                'frame_consistency' => 90,
                'downloaded_signature_risk' => 0,
            ];

            if (isset($fileInfo['error'])) {
                $breakdown['downloaded_signature_risk'] = 30;
            } else {
                $encoder = strtolower($fileInfo['video']['encoder'] ?? $fileInfo['quicktime']['moov']['subatoms'][0]['major_brand'] ?? '');
                
                $suspiciousEncoders = ['lavf', 'premiere', 'handbrake', 'obs', 'x264', 'vlc', 'capcut'];
                
                $isEdited = false;
                foreach ($suspiciousEncoders as $kw) {
                    if (str_contains($encoder, $kw)) {
                        $isEdited = true;
                        break;
                    }
                }
                
                if ($isEdited) {
                    $breakdown['downloaded_signature_risk'] = 90;
                    $breakdown['screen_recording_authenticity'] = 20;
                    $breakdown['fraud_flag'] = 'Non-native or edited video encoder detected';
                } elseif ($encoder === 'qt  ' || $encoder === 'isom' || str_contains($encoder, 'apple')) {
                    // Likely native mobile screen recording
                    $breakdown['screen_recording_authenticity'] = 98;
                }
            }
            $score = $breakdown['screen_recording_authenticity'] - ($breakdown['downloaded_signature_risk'] / 2);
        } elseif ($mimeType === 'application/pdf') {
            $breakdown = [
                'pdf_metadata_validity' => 90,
                'bank_signature_presence' => 80,
                'modification_risk' => 0,
            ];

            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($filePath);
                $text = strtolower($pdf->getText());
                
                $details = $pdf->getDetails();
                $producer = strtolower($details['Producer'] ?? '');
                $creator = strtolower($details['Creator'] ?? '');
                
                $suspicious = ['itext', 'canva', 'illustrator', 'photoshop', 'word', 'pdf24'];
                $isEdited = false;
                foreach ($suspicious as $kw) {
                    if (str_contains($producer, $kw) || str_contains($creator, $kw)) {
                        $isEdited = true;
                        break;
                    }
                }
                
                if ($isEdited) {
                    $breakdown['modification_risk'] = 90;
                    $breakdown['pdf_metadata_validity'] = 20;
                    $breakdown['fraud_flag'] = 'PDF generated by non-banking software';
                }
                
                // Keyword check
                $keywords = ['utr', 'upi', 'transfer', 'balance', 'credit', 'debit', 'transaction', 'ref', 'reference', 'rupees', 'rs'];
                $found = 0;
                foreach ($keywords as $kw) {
                    if (str_contains($text, $kw)) {
                        $found++;
                    }
                }
                
                if ($found < 2) {
                    $breakdown['bank_signature_presence'] = 10; // Probably fake or generic document
                    $breakdown['fraud_flag'] = 'Missing financial text signatures in PDF';
                } else {
                    $breakdown['bank_signature_presence'] = 95;
                }
                
            } catch (\Exception $e) {
                // If it can't parse, might be an image wrapped in PDF
                $breakdown['modification_risk'] = 50;
                $breakdown['bank_signature_presence'] = 0;
            }
            
            $score = (($breakdown['pdf_metadata_validity'] + $breakdown['bank_signature_presence']) / 2) - $breakdown['modification_risk'];
        }

        $score = max(0, min(100, (int) $score));

        // Deduct score if file was previously used across system
        $isDuplicate = FraudHash::where('file_hash', $hash)->exists();
        if ($isDuplicate) {
            $score = 10;
            $breakdown['file_authenticity'] = 0;
            $breakdown['fraud_flag'] = 'Duplicate file hash detected across system';
        }

        return [
            'score' => $score,
            'breakdown' => $breakdown,
            'is_duplicate' => $isDuplicate,
        ];
    }

    /**
     * Generate comparative recommendation between buyer & seller proof analyses
     */
    public function compareDisputeProofs(?array $buyerAnalysis, ?array $sellerAnalysis): array
    {
        $buyerScore = $buyerAnalysis['combined_score'] ?? ($buyerAnalysis['score'] ?? 50);
        $sellerScore = $sellerAnalysis['combined_score'] ?? ($sellerAnalysis['score'] ?? 50);

        if ($buyerScore > $sellerScore) {
            $recommendation = 'resolved_buyer';
            $confidence = min(95, 50 + ($buyerScore - $sellerScore));
            $reasoning = "Buyer proof score ({$buyerScore}) is significantly higher than seller score ({$sellerScore}).";
        } else {
            $recommendation = 'resolved_seller';
            $confidence = min(95, 50 + ($sellerScore - $buyerScore));
            $reasoning = "Seller proof score ({$sellerScore}) is higher or equal to buyer score ({$buyerScore}).";
        }

        return [
            'recommendation' => $recommendation,
            'confidence'     => $confidence,
            'reasoning'      => $reasoning,
        ];
    }
}
