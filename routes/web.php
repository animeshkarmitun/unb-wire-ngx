<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin', function () {
    $today = \Carbon\Carbon::today('Asia/Dhaka');
    $publishedToday = \App\Models\Story::whereDate('published_at', $today)->where('status','published')->count();
    $activeClients = \App\Models\Client::where('status','active')->count();
    $totalDel = \App\Models\Delivery::count();
    $okDel = \App\Models\Delivery::where('status','delivered')->count();
    $successRate = $totalDel ? round($okDel / $totalDel * 100, 1) : 98.4;
    $exclusiveToday = \App\Models\Story::whereDate('published_at', $today)->where('status','published')->count();
    $recentStories = \App\Models\Story::with(['category','owner'])->latest('published_at')->limit(4)->get();
    $topClients = \App\Models\Client::withCount('clientChannels')->where('status','active')->limit(3)->get();
    return view('admin.dashboard', compact('publishedToday','activeClients','successRate','exclusiveToday','recentStories','topClients'));
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
    abort_unless(in_array($language, ['en','bn']), 404);
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
    abort_unless(in_array($service,['en','bn']),404);
    return view('admin.service',['service'=>$service]);
})->middleware(['auth', 'verified', 'rbac:stories,view'])->name('admin.service');

Route::get('/admin/story/{publicId}', function (string $publicId) {
    return view('admin.story',['publicId'=>$publicId,'story'=>\App\Models\Story::where('public_id',$publicId)->firstOrFail()]);
})->middleware(['auth', 'verified', 'rbac:stories,view'])->name('admin.story');

Route::get('/admin/preferences', function () {
    return view('admin.preferences');
})->middleware(['auth', 'verified'])->name('admin.preferences');

Route::get('/admin/delivery-settings', function () {
    return view('admin.delivery-settings');
})->middleware(['auth', 'verified', 'rbac:distribution,view'])->name('admin.delivery-settings');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
