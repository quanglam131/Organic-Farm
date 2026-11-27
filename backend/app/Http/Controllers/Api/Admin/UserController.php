<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/** Administrator: customers and staff accounts (nhân sự), roles and locking. */
class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->select(['id', 'name', 'email', 'phone', 'role', 'points_balance', 'is_active', 'created_at'])
            ->latest('id');

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }
        if ($term = trim((string) $request->query('q'))) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
        }

        return response()->json($query->paginate(20));
    }

    /** Create an employee account (farm staff, sales or another admin). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', 'in:'.User::ROLE_STAFF.','.User::ROLE_SALES.','.User::ROLE_ADMIN],
            'password' => ['required', 'string', Password::min(8)],
        ]);

        // The "hashed" cast stores the password with Bcrypt.
        $user = User::create([...$data, 'is_active' => true]);

        return response()->json($user->only(['id', 'name', 'email', 'phone', 'role', 'is_active']), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'role' => ['sometimes', 'in:'.implode(',', User::ROLES)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($user->id === $request->user()->id && (($data['is_active'] ?? true) === false || ($data['role'] ?? User::ROLE_ADMIN) !== User::ROLE_ADMIN)) {
            throw new DomainException('Không thể tự khóa hoặc tự hạ quyền tài khoản của chính mình.');
        }

        $user->update($data);

        // A locked account is signed out everywhere.
        if (($data['is_active'] ?? true) === false) {
            $user->tokens()->delete();
        }

        return response()->json($user->only(['id', 'name', 'email', 'role', 'is_active']));
    }
}
