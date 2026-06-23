<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game; // Tu modelo de juegos
use Illuminate\Http\JsonResponse;

class GameController extends Controller
{
    public function index(): JsonResponse
    {
        // Devolvemos todos los juegos de la base de datos
        $games = Game::all();
        
        return response()->json($games);
    }
}