<?php
// app/Http/Requests/Auth/RegisterRequest.php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Solo usuarios no autenticados pueden registrarse
        return !auth()->check();
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'], 
            //'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'         => 'Este correo ya está registrado.',
            'password.confirmed'   => 'Las contraseñas no coinciden.',
            'password.min'         => 'La contraseña debe tener al menos 8 caracteres.',
        ];
    }
}