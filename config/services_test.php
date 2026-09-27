<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    // Preserve replaceable clients while exercising the real compiled application wiring.
    $container->services()->alias('test.jev.client', 'system_one.jev.client')->public();
    $container->services()->alias('test.clm.client', 'system_one.clm.client')->public();
};
