<?php

namespace HighsideLabs\LaravelSpApi;

use HighsideLabs\LaravelSpApi\Models\Credentials;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use SellingPartnerApi\Seller\SellerConnector;
use SellingPartnerApi\Vendor\VendorConnector;

class SellingPartnerApiServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap the application events.
     */
    public function boot(): void
    {
        // Publish config file
        $this->publishes([__DIR__.'/../config/spapi.php' => config_path('spapi.php')]);

        // Publish spapi_sellers and spapi_credentials migrations
        $migrationsDir = __DIR__.'/../database/migrations';
        $sellersMigrationFile = 'create_spapi_sellers_table.php';
        $credentialsMigrationFile = 'create_spapi_credentials_table.php';
        $this->publishesMigrations([
            "$migrationsDir/$sellersMigrationFile" => database_path("migrations/$sellersMigrationFile"),
            "$migrationsDir/$credentialsMigrationFile" => database_path("migrations/$credentialsMigrationFile"),
        ], 'spapi-migrations');

        // Don't offer the option to publish the package version upgrade migration unless this is a multi-seller
        // installation that was using dynamic AWS credentials (a feature that is now deprecated/irrelevant)
        if (config('spapi.installation_type') === 'multi' && config('spapi.aws.dynamic')) {
            $v2MigrationFile = 'upgrade_to_laravel_spapi_v2.php';
            $this->publishesMigrations([
                "$migrationsDir/$v2MigrationFile" => database_path("migrations/$v2MigrationFile"),
            ], 'spapi-v2-upgrade');
        }
    }

    /**
     * Register bindings in the container.
     */
    public function register(): void
    {
        if (config('spapi.installation_type') === 'single') {
            $creds = new Credentials([
                'client_id' => config('spapi.single.lwa.client_id'),
                'client_secret' => config('spapi.single.lwa.client_secret'),
                'refresh_token' => config('spapi.single.lwa.refresh_token'),
                'region' => config('spapi.single.endpoint'),
            ]);

            $this->app->bind(SellerConnector::class, fn () => $creds->sellerConnector());
            $this->app->bind(VendorConnector::class, fn () => $creds->vendorConnector());
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        if (config('spapi.installation_type') === 'single') {
            return [SellerConnector::class, VendorConnector::class];
        }

        return [];
    }
}
