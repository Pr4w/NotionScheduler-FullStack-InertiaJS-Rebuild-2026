<?php

namespace App\Http\Controllers;

use App\Models\Payments;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AffiliateController extends Controller
{
    /**
     * Affiliate dashboard: referral stats for the authenticated user.
     */
    public function index(): Response
    {
        $affiliate = Auth::user()->affiliates;

        if (! $affiliate) {
            return Inertia::render('app/Affiliates', [
                'enrolled' => false,
                'referralName' => null,
                'stats' => null,
            ]);
        }

        $affiliateRate = 0.2; // 20%

        $signups = User::where('affiliate_parent', $affiliate->userid)->pluck('id');
        $signupsCount = $signups->count();

        $payments = Payments::whereIn('userid', $signups)->pluck('payment_net');
        $conversions = $payments->count();
        $earnings = $payments->sum() * $affiliateRate;

        $paidOut = 0; // TODO: payouts

        return Inertia::render('app/Affiliates', [
            'enrolled' => true,
            'referralName' => $affiliate->name,
            'stats' => [
                'signups' => $signupsCount,
                'conversions' => $conversions,
                'effectiveness' => $signupsCount > 0 ? round(($conversions / $signupsCount) * 100, 2) : 0,
                'earnings' => round($earnings, 2),
                'balance' => round($earnings - $paidOut, 2),
                'paidOut' => $paidOut,
            ],
        ]);
    }
}
