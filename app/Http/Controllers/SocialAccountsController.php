<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Auth;

use App\Models\NotionSocialAccounts;

use Illuminate\Support\Facades\Response;




class SocialAccountsController extends Controller
{

    public function getAllSocialAccounts() {

        // Perform query
        $accounts = NotionSocialAccounts::where('userid', Auth::id())
            ->where('is_active', 1)
            ->where('is_valid', 1)
            ->get();

        return Response::default('OK', $accounts);

    }
    

}