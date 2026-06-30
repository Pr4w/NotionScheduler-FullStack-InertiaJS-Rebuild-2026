<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use App\Models\User;
use App\Models\StripeAccounts;

use App\Mail\SupportRequest;
use Illuminate\Support\Facades\Mail;

use Illuminate\Support\Facades\Response;

class UserController extends Controller {

    public function changePassword(Request $request) {

        // INIT
        $errors = [];

        // Do validator
        $validator = Validator::make(
            $request->all(),
            [
                'current_password' => ['required'],
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
            ]
        );

        if ($validator->fails()) {
            $validator_errors = $validator->errors()->toArray();
            foreach ($validator_errors as $va) {
                $errors[] = [
                    'type' => 'warning',
                    'message' => $va[0]
                ];
            }

            return Response::default(
                'FAIL',
                [],
                $errors
            );
        }

        // Everything has succeeded, lets check
        $user = User::find($request->user()->id);

        // Check the password
        if (!Hash::check($request->current_password, $user->password)) {
            return Response::failWithMessage(
                'warning',
                "The current password you typed is incorrect."
            );
        }

        // Password is correct, lets update
        $user->password = Hash::make($request->password);
        $user->save();

        // Return
        return Response::successWithMessage("Your password was successfully updated.");


    }

    public function changeEmail(Request $request) {

        // INIT
        $errors = [];

        // Do validator
        $validator = Validator::make(
            $request->all(),
            [
                'email' => ['required', 'string', 'email', 'max:255'],
                'password' => ['required'],
            ]
        );

        if ($validator->fails()) {
            $validator_errors = $validator->errors()->toArray();
            foreach ($validator_errors as $va) {
                $errors[] = [
                    'type' => 'warning',
                    'message' => $va[0]
                ];
            }

            return Response::default(
                'FAIL',
                [],
                $errors
            );
        }

        // Everything has succeeded, lets check
        $user = User::find($request->user()->id);

        // Check the password
        if (!Hash::check($request->password, $user->password)) {
            return Response::failWithMessage('warning', 'Incorrect password.');
        }

        // Check if it's not the same as the one we already have
        if ($request->email == $user->email) {
            return Response::failWithMessage('warning', 'Your new email has to be different from the previous one.');
        }

        // Check if the email already exists
        $check = User::where('email', $request->email)->get();

        // Check
        if ($check->count() > 0) {
            return Response::failWithMessage('warning', 'That email address is already in use.');
        }

        // All good, edit the email address
        $user->email = $request->email;
        $user->save();

        // Return
        return Response::successWithMessage("Your email was successfully updated.");

    }

    public function deleteAccount(Request $request) {

        // Get the user
        $user = User::find($request->user()->id);

        // Delete him
        $user->is_active = 0;
        $user->save();

        return Response::default();


    }

    public function getAnalytics() {

        // Init
        $data = [];

        // Get active socials
        $data['active_socials'] = Auth::user()->getTotalSocialAccountsConnectedToDatabases();
        $data['active_databases'] = Auth::user()->getActiveDatabaseCount();

        // Return
        return Response::default(
            'OK',
            $data,
            []
        );

    }

    public function getSupport(Request $request) {

        // INIT
        $errors = [];

        // Do validator
        $validator = Validator::make(
            $request->all(),
            [
                'message' => ['required', 'min:50', 'max:1000'],
            ]
        );

        if ($validator->fails()) {
            $validator_errors = $validator->errors()->toArray();
            foreach ($validator_errors as $va) {
                $errors[] = [
                    'type' => 'warning',
                    'message' => $va[0]
                ];
            }

            return Response::default(
                'FAIL',
                [],
                $errors
            );
        }

        
        Mail::to("mark@markhadjhamou.com")
            // ->replyTo($request->user()->email)
            ->send(new SupportRequest($request->user()->toArray(), $request->message));

        return Response::successWithMessage("Email successfully sent, we'll get back to you ASAP.");

    }

    public function generateBillingPortalUrl(Request $request) {

        // Generate URL
        try {
            $url = $request->user()->billingPortalUrl(
                config('app.frontend_url') . '/app/settings/profile'
            );
            return Response::default(
                'OK',
                [
                    'url' => $url
                ],
                []            
            );
        } catch (\Exception $e) {

            // Get message
            $msg = $e->getMessage();

            // Check 
            if (Str::startsWith($msg, "User is not a Stripe customer yet")) {
                return Response::failWithMessage('warning', "You are not a customer yet. You need to make a purchase before you can access the Billing Portal to manage your purchases.");
            }

            return Response::failWithMessage('danger', "Unhandled error - $msg");

        }

    }

}