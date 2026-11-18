<?php

use App\Http\Controllers\MediaFileController;
use App\Http\Controllers\ProfileController;
use App\Models\Story;
use App\Services\DashboardService;
use App\Services\DocxImportService;
use App\Services\RbacService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin', function (DashboardService $dashboardService) {
    return view('admin.dashboard', $dashboardService->getData(auth()->user()));
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

Route::get('/admin/news/import-sample', function () {
    return response(app(DocxImportService::class)->generateSample(), 200, [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'Content-Disposition' => 'attachment; filename="UNB-wire-news-sample.docx"',
    ]);
})->middleware(['auth', 'verified', 'rbac:stories,create'])->name('admin.news.import-sample');

Route::get('/admin/news/{language}', function (string $language) {
    abort_unless(in_array($language, ['en', 'bn']), 404);

    // COS-63: Bangla News is gated by the stories_bn module, not 'stories'.
    $rbac = app(RbacService::class);
    abort_unless($rbac->can(auth()->user(), $rbac->storiesModuleFor($language), 'view'), 403);

    return view('admin.news', ['language' => $language]);
})->middleware(['auth', 'verified'])->name('admin.news');

Route::get('/admin/photos', function () {
    return view('admin.photos');
})->middleware(['auth', 'verified', 'rbac:media,view'])->name('admin.photos');

Route::get('/admin/ai-settings', function () {
    return view('admin.ai-settings');
})->middleware(['auth', 'verified', 'rbac:settings,view'])->name('admin.ai-settings');

// COS-63: language-aware gate lives in AddNews::mount (stories vs stories_bn)
// because the wizard decides the module from the story/selected language.
Route::get('/admin/add-news', function () {
    return view('admin.add-news');
})->middleware(['auth', 'verified'])->name('admin.add-news');

Route::get('/admin/ap-photos', function () {
    return view('admin.ap-photos');
})->middleware(['auth', 'verified', 'rbac:media,view'])->name('admin.ap-photos');

Route::get('/admin/distribution', function () {
    return view('admin.distribution');
})->middleware(['auth', 'verified', 'rbac:distribution,view'])->name('admin.distribution');

Route::get('/admin/service/{service}', function (string $service) {
    abort_unless(in_array($service, ['en', 'bn']), 404);

    // COS-63: language-aware service page gate.
    $rbac = app(RbacService::class);
    abort_unless($rbac->can(auth()->user(), $rbac->storiesModuleFor($service), 'view'), 403);

    return view('admin.service', ['service' => $service]);
})->middleware(['auth', 'verified'])->name('admin.service');

Route::get('/admin/story/{publicId}', function (string $publicId) {
    $story = Story::where('public_id', $publicId)->firstOrFail();

    // COS-63: gate by the story's language module.
    $rbac = app(RbacService::class);
    abort_unless($rbac->can(auth()->user(), $rbac->storiesModuleFor($story->language), 'view'), 403);

    return view('admin.story', ['publicId' => $publicId, 'story' => $story]);
})->middleware(['auth', 'verified'])->name('admin.story');

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

Route::get('/admin/media/{asset}/file', [MediaFileController::class, 'staff'])
    ->middleware(['auth', 'verified', 'rbac:media,view'])->name('admin.media.file');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
