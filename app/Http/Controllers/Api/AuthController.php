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
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Devuelve solo los campos que el frontend necesita.
     * Centralizado aquí para que register() y login() devuelvan
     * exactamente el mismo shape — consistencia garantizada.
     */
    private function userResponse(User $user): array
    {
        return [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
        ];
    }

    // ─────────────────────────────────────────────────────────
    // REGISTRO
    // ─────────────────────────────────────────────────────────

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        Auth::login($user);

        // ✅ Regenerar el ID de sesión también tras el registro
        // Mismo vector de Session Fixation que en el login
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Cuenta creada con éxito.',
            'user'    => $this->userResponse($user),
        ], 201);
    }

    // ─────────────────────────────────────────────────────────
    // LOGIN
    // ─────────────────────────────────────────────────────────

    public function login(LoginRequest $request): JsonResponse
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            // ✅ ValidationException en lugar de response()->json() manual:
            // - Formato consistente con el resto de errores de validación
            // - El throttle de Laravel funciona mejor con excepciones
            // - El mensaje apunta al campo 'email' para no revelar
            //   si fue el email o la contraseña lo que falló
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        // ✅ Regenerar el ID de sesión tras autenticación exitosa
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Sesión iniciada correctamente.',
            'user'    => $this->userResponse(Auth::user()),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // LOGOUT
    // ─────────────────────────────────────────────────────────

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        // ✅ invalidate() destruye los datos de la sesión actual
        // ✅ regenerateToken() emite un nuevo token CSRF —
        //    el token antiguo queda inválido, protegiendo contra
        //    ataques donde el atacante conocía el CSRF token previo
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }
}