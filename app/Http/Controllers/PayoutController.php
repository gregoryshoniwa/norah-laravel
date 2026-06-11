<?php

namespace App\Http\Controllers;

use App\Models\Payout;
use App\Models\PayoutMessage;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PayoutBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Self-service payouts for the recipient (ADMIN or MERCHANT).
 * - Expected payout (currently owed, broken down by payment method)
 * - History (past payouts received)
 * - Confirm receipt of a SENT payout
 * - Queries: list / create / reply / view single thread
 */
class PayoutController extends Controller
{
    protected PayoutBalanceService $balances;

    public function __construct(PayoutBalanceService $balances)
    {
        $this->balances = $balances;
    }

    /**
     * GET /api/v1/payouts/expected
     * Returns the current expected payout for the logged-in user, grouped by
     * currency and broken down by payment method.
     */
    public function expected(Request $request)
    {
        $user = JWTAuth::user();
        if (!in_array($user->role, ['ADMIN', 'MERCHANT'], true)) {
            return response()->json(['message' => 'Payouts are not available for this role.'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->balances->expectedFor($user),
        ]);
    }

    /**
     * GET /api/v1/payouts
     * List own past payouts (paginated).
     */
    public function index(Request $request)
    {
        $user = JWTAuth::user();
        if (!in_array($user->role, ['ADMIN', 'MERCHANT'], true)) {
            return response()->json(['message' => 'Payouts are not available for this role.'], 403);
        }

        $query = Payout::where('recipient_user_id', $user->id);

        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->status));
        }
        if ($request->filled('currency')) {
            $query->where('currency', strtoupper($request->currency));
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderByDesc('created_at')->paginate((int) $request->get('per_page', 15)),
        ]);
    }

    /**
     * GET /api/v1/payouts/{id}
     * Show a payout with the per-transaction breakdown.
     */
    public function show($id)
    {
        $user = JWTAuth::user();
        $payout = Payout::with(['items.transaction:id,payment_method,amount,currency,trace,reference,customer_reference,created_at'])
            ->where('id', $id)
            ->where('recipient_user_id', $user->id)
            ->first();

        if (!$payout) {
            return response()->json(['message' => 'Payout not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $payout]);
    }

    /**
     * POST /api/v1/payouts/{id}/confirm
     * Recipient acknowledges that the bank transfer has landed.
     */
    public function confirm(Request $request, $id)
    {
        $user = JWTAuth::user();
        $payout = Payout::where('id', $id)
            ->where('recipient_user_id', $user->id)
            ->first();

        if (!$payout) {
            return response()->json(['message' => 'Payout not found.'], 404);
        }

        if ($payout->status !== 'SENT') {
            return response()->json([
                'message' => "Only payouts in SENT state can be confirmed. Current status: {$payout->status}.",
            ], 422);
        }

        $payout->update([
            'status' => 'CONFIRMED',
            'confirmed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payout confirmed. Thank you.',
            'data' => $payout->fresh(),
        ]);
    }

    /**
     * POST /api/v1/payouts/{id}/dispute
     * Recipient flags a problem with the payout.
     */
    public function dispute(Request $request, $id)
    {
        $user = JWTAuth::user();
        $validator = Validator::make($request->all(), [
            'body' => 'required|string|min:5|max:5000',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        $payout = Payout::where('id', $id)
            ->where('recipient_user_id', $user->id)
            ->first();
        if (!$payout) {
            return response()->json(['message' => 'Payout not found.'], 404);
        }

        $payout->update(['status' => 'DISPUTED', 'disputed_at' => now()]);

        $message = PayoutMessage::create([
            'payout_id' => $payout->id,
            'recipient_user_id' => $user->id,
            'sender_user_id' => $user->id,
            'sender_role' => $user->role,
            'subject' => "Dispute on payout #{$payout->id}",
            'body' => $request->body,
            'status' => 'OPEN',
        ]);

        return response()->json(['success' => true, 'data' => $message]);
    }

    /**
     * GET /api/v1/payouts/messages
     * Threads owned by the logged-in user. Each row is a root message with
     * a reply count and the latest reply timestamp.
     */
    public function listMessages(Request $request)
    {
        $user = JWTAuth::user();
        $threads = PayoutMessage::where('recipient_user_id', $user->id)
            ->whereNull('parent_message_id')
            ->withCount('replies')
            ->orderByDesc('updated_at')
            ->paginate((int) $request->get('per_page', 15));

        return response()->json(['success' => true, 'data' => $threads]);
    }

    /**
     * GET /api/v1/payouts/messages/{id}
     * Full thread (root + replies in order).
     */
    public function showThread($id)
    {
        $user = JWTAuth::user();
        $root = PayoutMessage::with(['replies.sender:id,first_name,last_name,email,role', 'sender:id,first_name,last_name,email,role'])
            ->where('id', $id)
            ->where('recipient_user_id', $user->id)
            ->first();

        if (!$root) {
            return response()->json(['message' => 'Thread not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $root]);
    }

    /**
     * POST /api/v1/payouts/messages
     * Start a new query / dispute.
     */
    public function createMessage(Request $request)
    {
        $user = JWTAuth::user();
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:120',
            'body' => 'required|string|min:5|max:5000',
            'payout_id' => 'nullable|integer|exists:payouts,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        if ($request->filled('payout_id')) {
            $owns = Payout::where('id', $request->payout_id)
                ->where('recipient_user_id', $user->id)
                ->exists();
            if (!$owns) {
                return response()->json(['message' => 'Payout not found for this user.'], 404);
            }
        }

        $message = PayoutMessage::create([
            'payout_id' => $request->payout_id,
            'recipient_user_id' => $user->id,
            'sender_user_id' => $user->id,
            'sender_role' => $user->role,
            'subject' => $request->subject,
            'body' => $request->body,
            'status' => 'OPEN',
        ]);

        return response()->json(['success' => true, 'data' => $message], 201);
    }

    /**
     * POST /api/v1/payouts/messages/{id}/reply
     */
    public function reply(Request $request, $id)
    {
        $user = JWTAuth::user();
        $validator = Validator::make($request->all(), [
            'body' => 'required|string|min:1|max:5000',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        $root = PayoutMessage::where('id', $id)
            ->whereNull('parent_message_id')
            ->where('recipient_user_id', $user->id)
            ->first();
        if (!$root) {
            return response()->json(['message' => 'Thread not found.'], 404);
        }

        $reply = PayoutMessage::create([
            'payout_id' => $root->payout_id,
            'recipient_user_id' => $root->recipient_user_id,
            'sender_user_id' => $user->id,
            'sender_role' => $user->role,
            'subject' => $root->subject,
            'body' => $request->body,
            'parent_message_id' => $root->id,
            'status' => 'OPEN',
        ]);

        $root->touch();

        return response()->json(['success' => true, 'data' => $reply], 201);
    }
}
