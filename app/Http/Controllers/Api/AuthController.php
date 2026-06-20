<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    private function userResponse(User $user): array
    {
        return [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
        ];
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Cuenta creada con éxito.',
            'user'    => $this->userResponse($user),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        // ✅ authenticate() ya contiene:
        //    - Rate limiting (5 intentos por email+IP)
        //    - Evento Lockout (para listeners o notificaciones)
        //    - Auth::attempt()
        //    - ValidationException si falla
        $request->authenticate();

        // ✅ Regenerar aquí, después de que authenticate() confirme
        //    que el usuario es válido — el Form Request no lo hace
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Sesión iniciada correctamente.',
            'user'    => $this->userResponse(Auth::user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }
}