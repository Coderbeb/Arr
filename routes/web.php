<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/login', function () {
    return view('auth.login');
})->name('login');

Route::get('/register', function () {
    return view('auth.register');
})->name('register');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        if (Auth::user()->role === 'super_admin') {
            return redirect()->route('admin');
        }
        return view('dashboard');
    })->name('dashboard');

    Route::get('/trade', function () {
        return view('trade');
    })->name('trade');

    Route::get('/wallet', function () {
        return view('wallet');
    })->name('wallet');

    Route::get('/assistance', function () {
        return view('assistance');
    })->name('assistance');

    Route::get('/admin', function () {
        return view('admin');
    })->name('admin');
});

// --------------------------------------------------------
// Stateful API Routes (Moved from api.php)
// --------------------------------------------------------
Route::prefix('api')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/register', [\App\Http\Controllers\AuthController::class, 'register']);
        Route::post('/login', [\App\Http\Controllers\AuthController::class, 'login']);

        Route::middleware('auth')->group(function () {
            Route::get('/me', [\App\Http\Controllers\AuthController::class, 'me']);
            Route::post('/logout', [\App\Http\Controllers\AuthController::class, 'logout']);
        });
    });

    Route::prefix('trade')->group(function () {
        Route::get('/amounts', [\App\Http\Controllers\TradeController::class, 'getAmounts']);

        Route::middleware('auth')->group(function () {
            Route::post('/sell', [\App\Http\Controllers\TradeController::class, 'sell']);
            Route::post('/buy/queue', [\App\Http\Controllers\TradeController::class, 'joinBuyerQueue']);
            Route::post('/pay/{trade_id}', [\App\Http\Controllers\TradeController::class, 'submitPayment']);
            Route::post('/confirm/{trade_id}', [\App\Http\Controllers\TradeController::class, 'confirm']);
            Route::post('/reject/{trade_id}', [\App\Http\Controllers\TradeController::class, 'reject']);
            Route::post('/cancel/{trade_id}', [\App\Http\Controllers\TradeController::class, 'cancel']);
            Route::post('/cancel-queue', [\App\Http\Controllers\TradeController::class, 'cancelQueue']);
            Route::post('/seller-cancel/{order_id}', [\App\Http\Controllers\TradeController::class, 'sellerCancel']);
            Route::get('/my-active', [\App\Http\Controllers\TradeController::class, 'getMyActiveTrade']);
            Route::get('/history', [\App\Http\Controllers\TradeController::class, 'history']);
        });
    });

    Route::prefix('wallet')->middleware('auth')->group(function () {
        Route::get('/balance', [\App\Http\Controllers\WalletController::class, 'getBalance']);
        Route::get('/transactions', [\App\Http\Controllers\WalletController::class, 'getTransactions']);
        Route::post('/claim-bonus/{milestone_id}', [\App\Http\Controllers\WalletController::class, 'claimBonus']);
    });

    Route::prefix('dispute')->middleware('auth')->group(function () {
        Route::post('/appeal/{trade_id}', [\App\Http\Controllers\DisputeController::class, 'appeal']);
        Route::get('/{dispute_id}', [\App\Http\Controllers\DisputeController::class, 'show']);
    });

    Route::prefix('assistance')->middleware('auth')->group(function () {
        Route::get('/queue', [\App\Http\Controllers\AssistanceController::class, 'queue']);
        Route::post('/resolve/{dispute_id}', [\App\Http\Controllers\AssistanceController::class, 'resolve']);
    });

    Route::prefix('admin')->middleware('auth')->group(function () {
        Route::get('/analytics', [\App\Http\Controllers\AdminController::class, 'analytics']);
        Route::post('/staff/create', [\App\Http\Controllers\AdminController::class, 'createStaff']);
        Route::post('/users/{user_id}/wallet-adjust', [\App\Http\Controllers\AdminController::class, 'adjustWallet']);
        
        Route::get('/users', [\App\Http\Controllers\AdminController::class, 'users']);
        Route::post('/users/{user_id}/status', [\App\Http\Controllers\AdminController::class, 'updateUserStatus']);
        Route::delete('/users/{user_id}', [\App\Http\Controllers\AdminController::class, 'deleteUser']);
        Route::get('/settings', [\App\Http\Controllers\AdminController::class, 'getSettings']);
        Route::post('/settings', [\App\Http\Controllers\AdminController::class, 'updateSettings']);
        Route::get('/audit-logs', [\App\Http\Controllers\AdminController::class, 'auditLogs']);
    });
});
