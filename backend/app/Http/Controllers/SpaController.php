<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the built React app (public/app/index.html) for every non-API path in production,
 * so the shop, the API and the QR trace links share one domain. React Router handles the path.
 */
class SpaController extends Controller
{
    public function __invoke(): Response
    {
        $spa = public_path('app/index.html');

        if (is_file($spa)) {
            return response()->file($spa, ['Cache-Control' => 'no-cache']);
        }

        // Development: the app runs on Vite instead.
        return response(
            config('farm.name').' API is running. Start the frontend with "npm run dev" in the frontend folder (http://localhost:5173).',
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }
}
