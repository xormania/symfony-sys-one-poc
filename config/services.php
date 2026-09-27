<?php

declare(strict_types=1);

use Symfony\AI\Platform\Bridge\TypeSafe\Factory;
use Symfony\AI\Platform\Bridge\TypeSafe\Jev;
use Symfony\AI\Platform\Bridge\TypeSafe\ModelCatalog;
use Symfony\AI\Platform\Platform;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()->defaults()->autowire()->autoconfigure();
    $services->load('App\\', '../src/')
        ->exclude(['../src/Kernel.php', '../src/Benchmark/Fixture.php', '../src/Benchmark/RunResult.php', '../src/Benchmark/Report.php', '../src/SystemOne/EvaluationOutcome.php', '../src/SystemOne/ProviderNotConfigured.php']);

    // The Bundle currently exposes neither a TypeSafe base_url nor named instances.
    // Reuse its unmodified bridge; only this local endpoint needs explicit wiring.
    $services->set('system_one.clm.catalog', ModelCatalog::class)->args([
        ['%env(CLM_MODEL)%' => ['class' => Jev::class, 'capabilities' => []]],
    ]);
    $services->set('system_one.clm.platform', Platform::class)
        ->factory([Factory::class, 'createPlatform'])
        ->arg('$apiKey', '%env(CLM_API_KEY)%')
        ->arg('$httpClient', service('system_one.clm.client'))
        ->arg('$modelCatalog', service('system_one.clm.catalog'))
        ->arg('$eventDispatcher', service('event_dispatcher'))
        ->arg('$name', 'clm')
        ->arg('$baseUrl', '%env(CLM_BASE_URL)%');
};
