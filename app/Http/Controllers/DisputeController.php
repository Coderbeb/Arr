<?php

namespace App\Http\Controllers;

use App\Models\Trade;
use App\Models\Dispute;
use App\Services\ProofAnalyzerService;
use App\Events\TradeStatusUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DisputeController extends Controller
{
    protected ProofAnalyzerService $proofService;

    public function __construct(ProofAnalyzerService $proofService)
    {
        $this->proofService = $proofService;
    }

    /**
     * POST /api/dispute/appeal/{trade_id}
     */
    public function appeal(Request $request, string $tradeId)
    {
        $request->validate([
            'screen_recording' => 'required|file|mimes:mp4,mov,avi,webm|max:51200',
            'bank_statement'   => 'required|file|mimes:pdf|max:10240',
            'screenshot'       => 'required|file|image|max:10240',
        ]);

        $buyer = $request->user();

        $trade = Trade::where('id', $tradeId)
            ->where('buyer_id', $buyer->id)
            ->where('status', 'seller_rejected')
            ->firstOrFail();

        $dispute = Dispute::where('trade_id', $trade->id)
            ->orderBy('created_at', 'desc')
            ->firstOrFail();

        if (Carbon::now()->greaterThan($dispute->proof_deadline)) {
            return response()->json(['error' => 'Appeal deadline has passed.'], 400);
        }

        $recordingUpload = $this->proofService->storeAndHashProof($request->file('screen_recording'), $buyer->id, $trade->id, $dispute->id, 'buyer_recording');
        $statementUpload = $this->proofService->storeAndHashProof($request->file('bank_statement'), $buyer->id, $trade->id, $dispute->id, 'buyer_statement');
        $screenshotUpload = $this->proofService->storeAndHashProof($request->file('screenshot'), $buyer->id, $trade->id, $dispute->id, 'buyer_screenshot');

        DB::transaction(function () use ($dispute, $trade, $recordingUpload, $statementUpload, $screenshotUpload) {
            $dispute->update([
                'buyer_screen_recording_url' => $recordingUpload['url'],
                'buyer_bank_statement_url'     => $statementUpload['url'],
                'buyer_screenshot_url'         => $screenshotUpload['url'],
                'buyer_proof_submitted_at'     => Carbon::now(),
                'status'                       => 'under_review',
            ]);

            $trade->update(['status' => 'disputed']);
        });

        // Run AI proof evaluation
        $recAnalysis = $this->proofService->analyzeProof($recordingUpload['hash'], $recordingUpload['proof_file']->mime_type, $recordingUpload['proof_file']->file_size, $buyer->id);
        $stmtAnalysis = $this->proofService->analyzeProof($statementUpload['hash'], $statementUpload['proof_file']->mime_type, $statementUpload['proof_file']->file_size, $buyer->id);
        $imgAnalysis = $this->proofService->analyzeProof($screenshotUpload['hash'], $screenshotUpload['proof_file']->mime_type, $screenshotUpload['proof_file']->file_size, $buyer->id);

        $combinedScore = round(($recAnalysis['score'] + $stmtAnalysis['score'] + $imgAnalysis['score']) / 3);

        $dispute->update([
            'buyer_ai_score'     => $combinedScore,
            'buyer_proof_analysis' => [
                'video' => $recAnalysis,
                'pdf' => $stmtAnalysis,
                'image' => $imgAnalysis
            ],
            'buyer_ai_breakdown' => [
                'video_score' => $recAnalysis['score'],
                'pdf_score' => $stmtAnalysis['score'],
                'image_score' => $imgAnalysis['score']
            ]
        ]);

        if ($dispute->seller_ai_score !== null) {
            $comparison = $this->proofService->compareDisputeProofs(
                ['combined_score' => $combinedScore],
                ['combined_score' => $dispute->seller_ai_score]
            );

            $dispute->update([
                'ai_recommendation' => $comparison['recommendation'],
                'ai_confidence'     => $comparison['confidence'],
            ]);
        }

        broadcast(new TradeStatusUpdated($trade))->toOthers();
        event(new \App\Events\UserActivityUpdated($trade->seller_id));

        return response()->json([
            'dispute_id' => $dispute->id,
            'message'    => 'Appeal submitted with 3 proofs. An assistant will review shortly.',
        ]);
    }

    /**
     * GET /api/dispute/{dispute_id}
     */
    public function show(Request $request, string $disputeId)
    {
        $user = $request->user();

        $dispute = Dispute::where('id', $disputeId)
            ->with(['trade', 'raisedBy:id,full_name,mobile_number', 'resolvedBy:id,full_name'])
            ->firstOrFail();

        if ($dispute->trade->buyer_id !== $user->id && $dispute->trade->seller_id !== $user->id && !in_array($user->role, ['assistance', 'super_admin'])) {
            return response()->json(['error' => 'Unauthorized access'], 403);
        }

        return response()->json($dispute);
    }
}
