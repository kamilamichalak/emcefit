<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $client = $request->user()->client;

        return Inertia::render('Client/Dashboard', [
            'makeupCredits' => $client
                ? $client->makeupCredits()->available()->count()
                : 0,
        ]);
    }
}
