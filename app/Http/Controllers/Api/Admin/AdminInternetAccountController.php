<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\InternetAccount;
use Illuminate\Http\Request;

class AdminInternetAccountController extends Controller
{
    public function index()
    {
        return response()->json(InternetAccount::orderBy('duration_type')->orderBy('status')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'duration_type' => 'required|in:daily,weekly,monthly',
        ]);

        $account = InternetAccount::create($request->only('username', 'password', 'duration_type'));

        return response()->json($account, 201);
    }

    public function destroy(InternetAccount $internetAccount)
    {
        $internetAccount->delete();

        return response()->json(['message' => 'Removed']);
    }
}
