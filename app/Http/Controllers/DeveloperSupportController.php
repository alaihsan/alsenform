<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDonationRequest;
use App\Http\Requests\StoreSuggestionRequest;
use App\Http\Requests\StoreWithdrawalRequest;
use App\Models\Donation;
use App\Models\Suggestion;
use App\Models\Withdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeveloperSupportController extends Controller
{
    /**
     * Submit user feedback / suggestion.
     */
    public function storeSuggestion(StoreSuggestionRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        $suggestion = Suggestion::query()->create([
            'user_id' => $request->user()->id,
            'subject' => $validated['subject'],
            'message' => $validated['message'],
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Saran & masukan Anda berhasil terkirim. Terima kasih atas kontribusi Anda!',
                'suggestion' => $suggestion,
            ]);
        }

        return back()->with('flash', [
            'success' => 'Saran & masukan Anda berhasil terkirim. Terima kasih atas kontribusi Anda!',
        ]);
    }

    /**
     * Initialize a new pending donation.
     */
    public function storeDonation(StoreDonationRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        $donation = Donation::query()->create([
            'user_id' => $request->user()->id,
            'donor_name' => $validated['donor_name'],
            'amount' => $validated['amount'],
            'message' => $validated['message'] ?? null,
            'status' => 'pending',
            'payment_reference' => 'DON-'.strtoupper(Str::random(10)),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'donation' => $donation,
            ]);
        }

        return back()->with('flash', [
            'active_donation' => $donation,
        ]);
    }

    /**
     * Simulate payment confirmation for a donation.
     */
    public function confirmDonation(Request $request, Donation $donation): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()->is($donation->user), 403);

        if ($donation->status !== 'success') {
            $donation->update([
                'status' => 'success',
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Pembayaran berhasil dikonfirmasi!',
                'donation' => $donation,
            ]);
        }

        return back()->with('flash', [
            'donation_success' => true,
            'donation_details' => $donation,
        ]);
    }

    /**
     * Retrieve statistics & logs for donations/withdrawals and suggestions.
     * Accessible only to admin users.
     */
    public function getStats(Request $request): JsonResponse
    {
        abort_unless($request->user()->is_admin, 403, 'Akses ditolak. Hanya untuk administrator.');

        $totalReceived = Donation::query()->where('status', 'success')->sum('amount');
        $totalWithdrawn = Withdrawal::query()->sum('amount');
        $balance = $totalReceived - $totalWithdrawn;

        $supporters = Donation::query()
            ->where('status', 'success')
            ->latest()
            ->take(10)
            ->get();

        $withdrawals = Withdrawal::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        $suggestions = Suggestion::query()
            ->with('user')
            ->latest()
            ->get();

        return response()->json([
            'total_received' => (int) $totalReceived,
            'total_withdrawn' => (int) $totalWithdrawn,
            'balance' => (int) $balance,
            'supporters' => $supporters,
            'withdrawals' => $withdrawals,
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * Withdraw developer balance.
     * Accessible only to admin users.
     */
    public function storeWithdrawal(StoreWithdrawalRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        $withdrawal = Withdrawal::query()->create([
            'user_id' => $request->user()->id,
            'amount' => $validated['amount'],
            'bank_name' => $validated['bank_name'],
            'account_number' => $validated['account_number'],
            'account_name' => $validated['account_name'],
            'status' => 'approved',
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Penarikan saldo sebesar Rp '.number_format($validated['amount'], 0, ',', '.').' berhasil diproses!',
                'withdrawal' => $withdrawal,
            ]);
        }

        return back()->with('flash', [
            'success' => 'Penarikan saldo sebesar Rp '.number_format($validated['amount'], 0, ',', '.').' berhasil diproses!',
        ]);
    }
}
