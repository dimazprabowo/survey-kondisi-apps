<?php

use App\Livewire\Actions\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::redirect('/', '/login');

// Logout Route (must be authenticated)
Route::post('/logout', function (Request $request, Logout $logout) {
    $logout();

    return redirect('/');
})->middleware('auth')->name('logout');

// Authenticated Routes
Route::middleware(['auth', 'verified', 'active'])->group(function () {

    // Dashboard
    Route::view('/dashboard', 'pages.dashboard')->name('dashboard');

    // Profile
    Route::view('profile', 'profile')->name('profile');

    // Master Data Routes
    Route::prefix('master-data')->name('master-data.')->group(function () {
        Route::view('/companies', 'master-data.companies')->middleware('can:companies_view')->name('companies');
        Route::view('/ships', 'master-data.ships')->middleware('can:ships_view')->name('ships');
        Route::view('/ships/create', 'master-data.ships-create')->middleware('can:ships_create')->name('ships.create');
        Route::get('/ships/{ship}/edit', function (\App\Models\Ship $ship) {
            return view('master-data.ships-edit', ['ship' => $ship]);
        })->middleware('can:ships_update')->name('ships.edit');

        // Template Form (manajemen template survey)
        Route::prefix('survey-templates')->name('survey-templates.')->group(function () {
            Route::view('/', 'templates.index')->middleware('can:survey_templates_view')->name('index');
            Route::view('/create', 'templates.create')->middleware('can:survey_templates_create')->name('create');
            Route::get('/{template}/edit', function (\App\Models\SurveyTemplate $template) {
                return view('templates.edit', ['template' => $template]);
            })->middleware('can:survey_templates_update')->name('edit');
            Route::get('/{template}', function (\App\Models\SurveyTemplate $template) {
                return view('templates.show', ['template' => $template]);
            })->middleware('can:survey_templates_view')->name('show');
        });
    });

    // Survey Kondisi
    Route::prefix('surveys')->name('surveys.')->group(function () {
        Route::view('/', 'surveys.index')->middleware('can:surveys_view')->name('index');
        Route::view('/create', 'surveys.create')->middleware('can:surveys_create')->name('create');
        Route::get('/{survey}/edit', function (\App\Models\Survey $survey) {
            return view('surveys.edit', ['survey' => $survey]);
        })->middleware('can:surveys_update')->name('edit');
        Route::get('/{survey}/report', function (\App\Models\Survey $survey) {
            return view('surveys.report', ['survey' => $survey]);
        })->middleware('can:survey_reports_view')->name('report');
        Route::get('/{survey}/report/benchmark-chart', function (\App\Models\Survey $survey) {
            $png = app(\App\Services\SurveyReportDocxBuilder::class)->renderBenchmarkChartPng(
                app(\App\Services\SurveyReportService::class)->benchmarkData($survey)
            );
            abort_if($png === null, 404);

            return response($png, 200, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'no-store',
            ]);
        })->middleware('can:survey_reports_view')->name('report.benchmark');
        Route::get('/{survey}/report/documentation/{categoryToken}', function (\App\Models\Survey $survey, string $categoryToken) {
            $categoryId = (int) \Illuminate\Support\Facades\Crypt::decryptString($categoryToken);
            $documentation = $survey->report?->documentations()
                ->where('survey_category_id', $categoryId)
                ->where('file_status', \App\Enums\FileStatus::Completed)
                ->firstOrFail();

            return app(\App\Services\FileStorageService::class)->inline($documentation->file_path, 'image/jpeg');
        })->middleware('can:survey_reports_view')->name('report.documentation');
        Route::get('/{survey}', function (\App\Models\Survey $survey) {
            return view('surveys.show', ['survey' => $survey]);
        })->middleware('can:surveys_view')->name('show');
    });

    // Notifications
    Route::view('/notifications', 'notifications.index')->middleware('can:notifications_view')->name('notifications.index');
    Route::view('/notifications/send', 'notifications.send')->middleware('can:notifications_send')->name('notifications.send');

    // Chat
    Route::view('/chat', 'chat.index')->middleware('can:chat_view')->name('chat.index');

    // Settings Routes - each route checks its own permission
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::view('/system', 'settings.system')->middleware('can:configuration_view')->name('system');
        Route::view('/users', 'settings.users')->middleware('can:users_view')->name('users');
        Route::view('/roles', 'settings.roles')->middleware('can:roles_view')->name('roles');
    });
});

require __DIR__.'/auth.php';
