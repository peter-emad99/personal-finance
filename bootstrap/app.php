<?php

use App\Console\Commands\BootstrapOwnerCommand;
use App\Console\Commands\McpServeCommand;
use App\Console\Commands\PurgeExpiredBackupsCommand;
use App\Console\Commands\UpdateMarketRatesCommand;
use App\Console\Commands\VerifyOperationsCommand;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequestContext;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([McpServeCommand::class, BootstrapOwnerCommand::class, VerifyOperationsCommand::class, PurgeExpiredBackupsCommand::class, UpdateMarketRatesCommand::class])
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('finance:verify-operations')->dailyAt('03:30')->withoutOverlapping();
        $schedule->command('finance:update-market-rates')->dailyAt('04:00')->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            RequestContext::class,
            SecurityHeaders::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
