<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function listUsers(): JsonResponse
    {
        $users = User::select('id', 'name', 'email', 'role', 'family_id', 'created_at')
            ->orderBy('role')
            ->orderBy('name')
            ->get();

        return response()->json($users);
    }

    public function createUser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users',
            'password'  => 'required|string|min:6',
            'role'      => 'required|in:ADMIN,PARENT',
            'family_id' => 'nullable|string|max:50',
        ]);

        $user = User::create($data);

        return response()->json([
            'id'        => $user->id,
            'name'      => $user->name,
            'email'     => $user->email,
            'role'      => $user->role,
            'family_id' => $user->family_id,
        ], 201);
    }

    public function assignFamily(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'family_id' => 'nullable|string|max:50',
        ]);

        $user = User::findOrFail($id);
        $user->update(['family_id' => $data['family_id']]);

        return response()->json([
            'id'        => $user->id,
            'name'      => $user->name,
            'email'     => $user->email,
            'role'      => $user->role,
            'family_id' => $user->family_id,
        ]);
    }
}
