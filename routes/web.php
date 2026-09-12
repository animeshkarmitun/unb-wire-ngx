<?php

use App\Http\Controllers\ProfileController;
use App\Models\Story;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin', function (DashboardService $dashboardService) {
    return view('admin.dashboard', $dashboardService->getData());
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/admin/roles', function () {
    return view('admin.roles');
})->middleware(['auth', 'verified', 'rbac:settings,view'])->name('admin.roles');

Route::get('/admin/packages', function () {
    return view('admin.packages');
})->middleware(['auth', 'verified', 'rbac:packages,view'])->name('admin.packages');

Route::get('/admin/clients', function () {
    return view('admin.clients');
})->middleware(['auth', 'verified', 'rbac:clients,view'])->name('admin.clients');

Route::get('/admin/news/{language}', function (string $language) {
    abort_unless(in_array($language, ['en', 'bn']), 404);

    return view('admin.news', ['language' => $language]);
})->middleware(['auth', 'verified', 'rbac:stories,view'])->name('admin.news');

Route::get('/admin/photos', function () {
    return view('admin.photos');
})->middleware(['auth', 'verified', 'rbac:media,view'])->name('admin.photos');

Route::get('/admin/ai-settings', function () {
    return view('admin.ai-settings');
})->middleware(['auth', 'verified', 'rbac:settings,view'])->name('admin.ai-settings');

Route::get('/admin/add-news', function () {
    return view('admin.add-news');
})->middleware(['auth', 'verified', 'rbac:stories,create'])->name('admin.add-news');

Route::get('/admin/ap-photos', function () {
    return view('admin.ap-photos');
})->middleware(['auth', 'verified', 'rbac:media,view'])->name('admin.ap-photos');

Route::get('/admin/distribution', function () {
    return view('admin.distribution');
})->middleware(['auth', 'verified', 'rbac:distribution,view'])->name('admin.distribution');

Route::get('/admin/service/{service}', function (string $service) {
    abort_unless(in_array($service, ['en', 'bn']), 404);

    return view('admin.service', ['service' => $service]);
})->middleware(['auth', 'verified', 'rbac:stories,view'])->name('admin.service');

Route::get('/admin/story/{publicId}', function (string $publicId) {
    return view('admin.story', ['publicId' => $publicId, 'story' => Story::where('public_id', $publicId)->firstOrFail()]);
})->middleware(['auth', 'verified', 'rbac:stories,view'])->name('admin.story');

Route::get('/admin/preferences', function () {
    return view('admin.preferences');
})->middleware(['auth', 'verified'])->name('admin.preferences');

Route::get('/admin/delivery-settings', function () {
    return view('admin.delivery-settings');
})->middleware(['auth', 'verified', 'rbac:distribution,view'])->name('admin.delivery-settings');

Route::get('/admin/audit', function () {
    return view('admin.audit');
})->middleware(['auth', 'verified', 'rbac:audit,view'])->name('admin.audit');

Route::get('/admin/notifications', function () {
    return view('admin.notifications');
})->middleware(['auth', 'verified'])->name('admin.notifications');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
