<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        // We can add filtering here if they have a 'role' or 'type' column
        // e.g., $query->where('type', $request->type);

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('email', 'like', '%' . $request->search . '%');
        }

        $users = $query->latest()->paginate($request->get('limit', 15));

        return response()->json([
            'status' => 'success',
            'data' => $users
        ]);
    }
}
