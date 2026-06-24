<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
            'teacher' => $request->user()->teacher,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user()->loadMissing('teacher');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'highest_degree' => ['nullable', 'string', 'max:120'],
            'specialty' => ['nullable', 'string', 'max:160'],
            'employment_type' => ['nullable', 'string', 'max:80'],
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if ($user->teacher) {
            $user->teacher->update([
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'highest_degree' => $data['highest_degree'] ?? null,
                'specialty' => $data['specialty'] ?? null,
                'employment_type' => $data['employment_type'] ?? null,
            ]);
        }

        return response()->json([
            'message' => 'Perfil actualizado correctamente.',
            'user' => $this->userPayload($user->fresh()),
            'teacher' => $user->fresh()->teacher,
        ]);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'confirmed',
                'different:current_password',
                Password::min(8)->letters()->numbers(),
            ],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['La contrasena actual no es correcta.'],
            ]);
        }

        if (in_array(strtolower($data['password']), ['password', '12345678', 'qwerty123'], true)) {
            throw ValidationException::withMessages([
                'password' => ['Usa una contrasena diferente a las claves temporales.'],
            ]);
        }

        $user->forceFill([
            'password' => $data['password'],
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        $currentTokenId = $user->currentAccessToken()?->id;
        if ($currentTokenId) {
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();
        }

        return response()->json([
            'message' => 'Contrasena actualizada correctamente.',
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    private function userPayload(User $user): array
    {
        $user->loadMissing('teacher');

        return array_merge($user->toArray(), [
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->getAllPermissions()
                ->map(fn ($permission) => ['name' => $permission->name])
                ->values(),
            'teacher' => $user->teacher,
        ]);
    }
}
