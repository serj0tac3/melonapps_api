<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth; // 🚀 VITAL: Importamos la clase Auth

class AuthController extends Controller
{
    /**
     * REGISTRO
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // 🚀 Iniciamos la SESIÓN WEB automáticamente tras el registro
        Auth::login($user);

        return response()->json([
            'message' => 'Usuario registrado con éxito',
            'user' => $user
        ], 201);
    }

    /**
     * LOGIN
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        // 🚀 Validamos las credenciales y creamos la SESIÓN WEB
        if (Auth::attempt($credentials)) {
            // Regenerar la sesión evita ataques de robo de sesión (Session Fixation)
            $request->session()->regenerate(); 

            return response()->json([
                'message' => 'Login exitoso',
                'user' => Auth::user()
            ]);
        }

        return response()->json([
            'message' => 'Las credenciales son incorrectas'
        ], 401);
    }

    /**
     * LOGOUT
     */
    public function logout(Request $request): JsonResponse
    {
        // 🚀 Cerramos la SESIÓN WEB y limpiamos las cookies
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Sesión cerrada correctamente'
        ]);
    }
}