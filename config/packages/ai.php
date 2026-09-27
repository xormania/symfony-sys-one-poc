<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('ai', [
        'platform' => [
            'typesafe' => [
                'api_key' => '%env(TYPESAFE_API_KEY)%',
                'http_client' => 'system_one.jev.client',
            ],
        ],
    ]);
};
