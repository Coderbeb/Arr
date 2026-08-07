<?php

namespace App\Http\Controllers;

use App\Models\Dispute;
use App\Models\Trade;
use App\Models\User;
use App\Models\AdminAuditLog;
use App\Services\WalletService;
use App\Events\TradeStatusUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AssistanceController extends Controller
{
    protected WalletService $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * GET /api/assistance/queue
     */
    public function queue(Request $request)
    {
        $user = $request->user();
        if (!in_array($user->role, ['assistance', 'super_admin'])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $disputes = Dispute::whereIn('status', ['pending', 'under_review', 'escalated'])
            ->with([
                'trade',
                'trade.buyer:id,full_name,mobile_number',
                'trade.seller:id,full_name,mobile_number',
                'raisedBy:id,full_name',
                'assignedTo:id,full_name'
            ])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($dispute) {
                $urlFields = [
                    'buyer_screenshot_url', 'buyer_screen_recording_url', 'buyer_bank_statement_url', 'buyer_upi_screenshot_url',
                    'seller_screen_recording_url', 'seller_txn_screenshot_url', 'seller_profile_recording_url', 'seller_bank_statement_url'
                ];
                foreach ($urlFields as $field) {
                    if (!empty($dispute->$field) && !str_starts_with($dispute->$field, 'http') && !str_starts_with($dispute->$field, '/storage/')) {
                        $dispute->$field = '/storage/' . ltrim($dispute->$field, '/');
                    }
                }
                return $dispute;
            });

        return response()->json($disputes);
    }

    /**
     * POST /api/assistance/claim/{dispute_id}
     */
    public function claim(Request $request, string $disputeId)
    {
        $admin = $request->user();
        if (!in_array($admin->role, ['assistance', 'super_admin'])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $dispute = Dispute::where('id', $disputeId)
            ->whereIn('status', ['pending', 'under_review', 'escalated'])
            ->firstOrFail();

        if ($dispute->assigned_to && $dispute->assigned_to !== $admin->id && $admin->role !== 'super_admin') {
            return response()->json(['error' => 'This dispute is already assigned to someone else.'], 400);
        }

        $dispute->update([
            'assigned_to' => $admin->id,
            'status' => 'under_review',
        ]);

        AdminAuditLog::create([
            'id'          => (string) Str::uuid(),
            'admin_id'    => $admin->id,
            'action'      => "claim_dispute",
            'target_type' => 'dispute',
            'target_id'   => $dispute->id,
            'notes'       => "Claimed dispute for review",
            'created_at'  => Carbon::now(),
        ]);

        return response()->json(['message' => 'Dispute claimed successfully.', 'dispute' => $dispute]);
    }

    /**
     * POST /api/assistance/resolve/{dispute_id}
     */
    public function resolve(Request $request, string $disputeId)
    {
        $request->validate([
            'winner' => 'required|string|in:buyer,seller',
            'notes'  => 'nullable|string',
        ]);

        $admin = $request->user();
        if (!in_array($admin->role, ['assistance', 'super_admin'])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $dispute = Dispute::where('id', $disputeId)
            ->whereIn('status', ['pending', 'under_review', 'escalated'])
            ->firstOrFail();

        if ($dispute->assigned_to !== $admin->id && $admin->role !== 'super_admin') {
            return response()->json(['error' => 'You must claim this dispute before resolving it, or it is assigned to someone else.'], 403);
        }

        $trade = Trade::where('id', $dispute->trade_id)->firstOrFail();

        DB::transaction(function () use ($dispute, $trade, $admin, $request) {
            $winner = $request->winner;
            $resolutionStatus = $winner === 'buyer' ? 'resolved_buyer' : 'resolved_seller';

            $dispute->update([
                'status'           => $resolutionStatus,
                'resolved_by'      => $admin->id,
                'resolution_notes' => $request->notes ?? "Manually resolved by support in favor of {$winner}",
                'resolved_at'      => Carbon::now(),
            ]);

            $tradeAmt = (float) $trade->amount;

            if ($winner === 'buyer') {
                // Release seller escrow and transfer coins to buyer
                $seller = User::where('id', $trade->seller_id)->lockForUpdate()->first();
                $buyer = User::where('id', $trade->buyer_id)->lockForUpdate()->first();

                $seller->escrow_balance -= $tradeAmt;
                $seller->save();

                $buyer->wallet_balance += $tradeAmt;
                $buyer->total_trades += 1;
                $buyer->save();

                $trade->update(['status' => 'completed', 'completed_at' => Carbon::now()]);
            } else {
                // Refund escrow back to seller wallet
                $seller = User::where('id', $trade->seller_id)->lockForUpdate()->first();
                $this->walletService->releaseEscrow(
                    $seller,
                    $tradeAmt,
                    "Dispute resolved in favor of seller. ₹{$tradeAmt} released from escrow.",
                    "विवाद विक्रेता के पक्ष में हल। ₹{$tradeAmt} एस्क्रो से वापस।"
                );

                $trade->update(['status' => 'refunded']);
            }

            AdminAuditLog::create([
                'id'          => (string) Str::uuid(),
                'admin_id'    => $admin->id,
                'action'      => "resolve_dispute_{$winner}",
                'target_type' => 'dispute',
                'target_id'   => $dispute->id,
                'notes'       => $request->notes,
                'created_at'  => Carbon::now(),
            ]);
        });

        broadcast(new TradeStatusUpdated($trade))->toOthers();
        event(new \App\Events\UserActivityUpdated($trade->buyer_id));
        event(new \App\Events\UserActivityUpdated($trade->seller_id));

        return response()->json(['message' => "Dispute resolved in favor of {$request->winner}."]);
    }
}
