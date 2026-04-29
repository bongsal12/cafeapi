<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Create a new user (admin only).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['sometimes', 'in:admin,staff'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'] ?? 'staff',
        ]);

        return response()->json(['user' => $user], 201);
    }

    /**
     * List users (admin only).
     */
    public function index()
    {
        return response()->json(User::orderBy('id','desc')->get(), 200);
    }

    /**
     * Update a user.
     */
    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['sometimes','string','max:255'],
            'email' => ['sometimes','email','max:255','unique:users,email,'.$user->id],
            'password' => ['sometimes','nullable','string','min:8'],
            'role' => ['sometimes','in:admin,staff'],
            'active' => ['sometimes','boolean'],
            'avatar' => ['sometimes','nullable','string'],
        ]);

        if (isset($data['password']) && $data['password']) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json(['user'=>$user], 200);
    }

    /**
     * Delete a user.
     */
    public function destroy(User $user)
    {
        User::destroy($user->id);
        return response()->json(['message'=>'User deleted'], 200);
    }
}
