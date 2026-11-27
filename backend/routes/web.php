<?php

use App\Http\Controllers\SpaController;
use Illuminate\Support\Facades\Route;

// Every path that is not an API, storage or health-check URL returns the React app (see SpaController).
Route::get('/{path?}', SpaController::class)->where('path', '^(?!api/|storage/|up$).*$');
