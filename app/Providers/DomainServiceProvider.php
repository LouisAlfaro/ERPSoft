<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Audits
use Src\Audits\Domain\Repositories\AuditRepository;
use Src\Audits\Infrastructure\Persistence\Eloquent\Repositories\EloquentAuditRepository;

// Organizations  👇  (FALTABAN ESTOS USE)
use Src\Organizations\Domain\Repositories\CompanyRepository;
use Src\Organizations\Domain\Repositories\LocalRepository;
use Src\Organizations\Infrastructure\Persistence\Eloquent\Repositories\EloquentCompanyRepository;
use Src\Organizations\Infrastructure\Persistence\Eloquent\Repositories\EloquentLocalRepository;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Audits
        $this->app->bind(AuditRepository::class, EloquentAuditRepository::class);

        // Organizations
        $this->app->bind(CompanyRepository::class, EloquentCompanyRepository::class);
        $this->app->bind(LocalRepository::class, EloquentLocalRepository::class);
    }

    public function boot(): void {}
}
