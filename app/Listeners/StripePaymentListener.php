<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

use Laravel\Cashier\Events\WebhookReceived;

use Illuminate\Support\Facades\Log;

use App\Models\User;
use Carbon\Carbon;
use App\Models\Payments;

class StripePaymentListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(WebhookReceived $event): void
    {
        if ($event->payload['type'] === 'checkout.session.completed') {

            // Make pretty
            $object = $event->payload['data']['object'];

            // Get the user from the customer id
            $user = User::where('stripe_id', $object['customer'])->first();

            // Add to DB
            $payment = Payments::create([
                'userid' => $user->id,

                'mode' => $object['mode'],

                'subscription' => $object['subscription'],
                'payment_intent' => $object['payment_intent'],
                'payment_invoice' => $object['invoice'],

                'customer' => $object['customer'],

                'payment_net' => $object['amount_subtotal'] / 100,
                'payment_tax' => $object['total_details']['amount_tax'] / 100,
                'payment_total' => $object['amount_total'] / 100,
                'payment_currency' => $object['currency'],

                'payer_tax_exempt' => $object['customer_details']['tax_exempt'],
                'payer_email' => $object['customer_details']['email'],
                'payer_fullname' => $object['customer_details']['name'],
                'payer_address1' => $object['customer_details']['address']['line1'],
                'payer_address2' => $object['customer_details']['address']['line2'],
                'payer_city' => $object['customer_details']['address']['city'],
                'payer_postcode' => $object['customer_details']['address']['postal_code'],
                'payer_state' => $object['customer_details']['address']['state'],
                'payer_country_code' => $object['customer_details']['address']['country'],
                'payer_country' => locale_get_display_region('-' . $object['customer_details']['address']['country'], 'en'),


                'payment_date' => Carbon::parse($object['created'])
            ]);
            
        }
    }
}
