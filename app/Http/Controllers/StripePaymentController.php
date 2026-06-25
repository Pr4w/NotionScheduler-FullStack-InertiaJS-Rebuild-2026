<?php

namespace App\Http\Controllers;

use App\Enums\StripePackages;
use App\Models\UserAffiliates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;

class StripePaymentController extends Controller
{
    // NOTE - URLs
    protected $success_url;

    protected $cancel_url;

    protected $packages;

    // NOTE - Construct, set defaults
    public function __construct()
    {
        $this->success_url = config('app.frontend_url').'/app/pricing?status=success';
        $this->cancel_url = config('app.frontend_url').'/app/pricing?status=cancel';

        // Log::info($this->success_url);
        // Log::info($this->cancel_url);

        // $this->packages = [
        //     'tier_1' => [
        //         'name' => 'Free',
        //         'description' => 'For beginners & small-time creators',
        //         'monthly' => 0,
        //         'yearly' => 0,
        //         'saved' => 0
        //     ],
        //     'tier_2' => [
        //         'name' => 'Basic',
        //         'description' => 'Perfect to get things started',
        //         'monthly' => [
        //             'price' => 10,
        //             'stripe' => "price_1P4JwOKEkR852Xithqu8va98"
        //         ],
        //         'yearly' => [
        //             'price' => 90,
        //             'stripe' => "price_1P4JvwKEkR852XitNdIVyksU"
        //         ],
        //         'saved' => 20,
        //     ],
        //     'tier_3' => [
        //         'name' => 'Advanced',
        //         'description' => 'For the pros!',
        //         'monthly' => [
        //             'price' => 25,
        //             'stripe' => "price_1P4JxBKEkR852XitbJGQeAKd"
        //         ],
        //         'yearly' => [
        //             'price' => 250,
        //             'stripe' => "price_1P4JxfKEkR852XitOGpM5zcE"
        //         ],
        //         'saved' => 50
        //     ]
        // ];

        $this->packages = [
            StripePackages::TIER_1->value => array_merge(
                StripePackages::TIER_1->prices(),
                StripePackages::TIER_1->options()
            ),
            StripePackages::TIER_2->value => array_merge(
                StripePackages::TIER_2->prices(),
                StripePackages::TIER_2->options()
            ),
            StripePackages::TIER_3->value => array_merge(
                StripePackages::TIER_3->prices(),
                StripePackages::TIER_3->options()
            ),
            StripePackages::TIER_4->value => array_merge(
                StripePackages::TIER_4->prices(),
                StripePackages::TIER_4->options()
            ),
        ];
    }

    // NOTE - Get all the packages
    public function returnPackages()
    {

        return Response::default(
            'OK',
            $this->packages,
            []
        );

    }

    // NOTE - Inertia pricing page
    public function page(Request $request): \Inertia\Response
    {

        return Inertia::render('app/Pricing', [
            'packages' => $this->packages,
            'currentTier' => $request->user()->getTier(),
        ]);

    }

    // NOTE - Get all packages and available discounts
    public function returnPackagesAndDiscounts(Request $request)
    {

        // INIT
        $data = [
            'packages' => $this->packages,
            'discounts' => null,
        ];

        // Check if there are available discounts
        $user = $request->user();
        if ($user->affiliate_parent) {
            $affiliate = UserAffiliates::where('userid', $user->affiliate_parent)->first();
            if ($affiliate) {
                if ($affiliate->stripe_coupon && $affiliate->discount_percentage) {
                    $data['discounts'] = [
                        'name' => $affiliate->name,
                        'discount_percentage' => $affiliate->discount_percentage,
                    ];
                }
            }
        }

        return Response::default(
            'OK',
            $data,
            []
        );

    }

    // NOTE - Generate a payment
    public function generatePayment(Request $request)
    {

        // Check if we have everything we need
        if (! isset($request->package) or ! isset($request->plan)) {
            return Response::failWithMessage('warning', 'No package or payment plan was selected');
        }
        if (! in_array($request->plan, ['monthly', 'yearly'])) {
            return Response::failWithMessage('warning', 'Malformed payment plan.');
        }
        if (! in_array($request->package, array_keys($this->packages))) {
            return Response::failWithMessage('warning', "This package or payment plan doesn't exist.");
        }

        // Get the price
        $price = $this->packages[$request->package][$request->plan]['stripe'];

        // Create checkout array
        $checkout = [
            'success_url' => $this->success_url,
            'cancel_url' => $this->cancel_url,
            'billing_address_collection' => 'required',
            'customer_update[address]' => 'auto',
            // 'allow_promotion_codes' => true,
            // 'automatic_tax' => ['enabled' => false], // NOTE - This is on by default
            // 'metadata' => [
            //     'bacon' => 'yolo' // FIXME -
            // ]
        ];

        // Check if the user has an affiliate discount
        $user = $request->user();
        if ($user->affiliate_parent) {
            $affiliate = UserAffiliates::where('userid', $user->affiliate_parent)->first();
            if ($affiliate) {
                if ($affiliate->stripe_coupon) {
                    $checkout['discounts'] = [['coupon' => $affiliate->stripe_coupon]];
                }
            }
        }

        // Add a coupon for our 500th user
        if ($user->id == 500) {
            $checkout['discounts'] = [['coupon' => 'JeiPkH7Z']];
        }

        // Generate what we need
        $stripe = $request->user()->newSubscription($request->package, $price)
            ->checkout($checkout);

        // Return the URL
        return Response::default(
            'OK',
            $stripe->url,
            []
        );

    }
}
