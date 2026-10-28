<?php

use Illuminate\Support\Facades\Route;

Route::view('/{path?}', 'game')->where('path', '^(?!api|up).*$');
