<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('framework', [
        'secret' => '%env(APP_SECRET)%',
        'http_client' => [
            'scoped_clients' => [
                'system_one.jev.client' => [
                    'base_uri' => 'https://api.typesafe.ai/',
                    'timeout' => '%env(float:SYSTEM_ONE_TIMEOUT)%',
                    'max_duration' => '%env(float:SYSTEM_ONE_TIMEOUT)%',
                    'max_redirects' => 0,
                ],
                'system_one.clm.client' => [
                    'base_uri' => '%env(CLM_BASE_URL)%',
                    'timeout' => '%env(float:SYSTEM_ONE_TIMEOUT)%',
                    'max_duration' => '%env(float:SYSTEM_ONE_TIMEOUT)%',
                    'max_redirects' => 0,
                ],
            ],
        ],
        'php_errors' => ['log' => true],
    ]);

    if ('test' === $container->env()) {
        $container->extension('framework', ['test' => true]);
    }
};
