<?php

use App\Http\Controllers\ExcelController;
use App\Http\Controllers\ForDifferentController;
use App\Http\Controllers\OldStyleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/excel');
});
Route::resource('excel', ExcelController::class);
Route::post('download', [ExcelController::class, 'download'])->name('download');

Route::get('/success', function () {
    return view('success');
});

Route::resource('differentExcel', ForDifferentController::class)->only(['index', 'store']);

Route::resource('oldstyleExcel', OldStyleController::class)->only(['index', 'store']);
