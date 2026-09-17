<?php

declare(strict_types=1);

namespace Plugins\Messages_stats;

use Plugins\Messages_stats\Controllers\StatsController;
use Pmsrapi\V2\Core\Container;
use Pmsrapi\V2\Database\Connection;
use Pmsrapi\V2\Database\Schema;
use Pmsrapi\V2\Http\Request;
use Pmsrapi\V2\Http\Response;
use Pmsrapi\V2\Plugin\AbstractPlugin;
use Pmsrapi\V2\Plugin\PluginRegistrar;
use Pmsrapi\V2\Plugin\PluginRouter;

final class Messages_statsPlugin extends AbstractPlugin
{
    public function register(PluginRegistrar $registrar): void
    {
        $registrar->singleton(
            StatsController::class,
            static fn(Container $c): StatsController => new StatsController(
                $c->get(Connection::class),
                $c->get(Schema::class),
            ),
        );
    }

    public function routes(PluginRouter $router, Container $container): void
    {
        $router->get('/', static fn(Request $r, array $p): Response
            => $container->get(StatsController::class)->stats($r));
    }
}