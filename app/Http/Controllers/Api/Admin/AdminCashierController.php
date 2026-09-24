<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;

/**
 * Cashier accounts can only ever be created here, by a full admin — there's
 * no public/self-registration path for staff accounts of any kind. Gated
 * by EnsureFullAdmin at the route level, not just checked here, so this
 * controller doesn't need to re-verify the caller's role itself.
 */
class AdminCashierController extends Controller
{
    public function index()
    {
        $cashiers = Admin::where('role', 'cashier')
            ->with('creator:id,name,email')
            ->latest()
            ->get(['id', 'name', 'email', 'role', 'created_by', 'created_at']);

        return response()->json($cashiers);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|unique:admins,email',
            'password' => 'required|string|min:8',
        ]);

        $cashier = Admin::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'role' => 'cashier',
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Cashier account created.', 'cashier' => $cashier], 201);
    }

    public function destroy(Admin $cashier)
    {
        if ($cashier->role !== 'cashier') {
            return response()->json(['message' => 'This endpoint only removes cashier accounts.'], 422);
        }

        $cashier->tokens()->delete();
        $cashier->delete();

        return response()->json(['message' => 'Cashier account removed.']);
    }
}
