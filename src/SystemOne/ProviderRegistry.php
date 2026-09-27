<?php

declare(strict_types=1);

namespace App\SystemOne;

use Psr\Container\ContainerInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

final readonly class ProviderRegistry
{
    public const array NAMES = ['jev', 'clm'];

    public function __construct(
        #[AutowireLocator(['jev' => new Autowire(service: 'ai.platform.typesafe'), 'clm' => new Autowire(service: 'system_one.clm.platform')])]
        private ContainerInterface $platforms,
        #[Autowire('%env(JEV_MODEL)%')]
        private string $jevModel,
        #[Autowire('%env(CLM_MODEL)%')]
        private string $clmModel,
        #[Autowire('%env(TYPESAFE_API_KEY)%'), \SensitiveParameter]
        private string $jevKey,
        #[Autowire('%env(CLM_BASE_URL)%')]
        private string $clmUrl,
    ) {
    }

    public function platform(string $provider): PlatformInterface
    {
        $this->model($provider);

        if ('jev' === $provider && '' === trim($this->jevKey)) {
            throw new ProviderNotConfigured('Set TYPESAFE_API_KEY in .env.local or the environment to run Jev.');
        }

        if ('clm' === $provider) {
            $url = parse_url($this->clmUrl);
            if (false === $url || !isset($url['host']) || !\in_array($url['scheme'] ?? '', ['http', 'https'], true) || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])) {
                throw new ProviderNotConfigured('CLM_BASE_URL must be an HTTP(S) server root without credentials, query, or fragment.');
            }
            if ('' !== trim($url['path'] ?? '', '/')) {
                throw new ProviderNotConfigured('CLM_BASE_URL must be the server root; the bridge appends /v1/systemone.');
            }
        }

        $platform = $this->platforms->get($provider);
        if (!$platform instanceof PlatformInterface) {
            throw new \LogicException('The configured provider must implement Symfony PlatformInterface.');
        }

        return $platform;
    }

    /** @return non-empty-string */
    public function model(string $provider): string
    {
        $model = match ($provider) {
            'jev' => $this->jevModel,
            'clm' => $this->clmModel,
            default => throw new \InvalidArgumentException('Unknown provider; choose jev or clm.'),
        };

        $model = trim($model);
        if ('' === $model) {
            throw new ProviderNotConfigured('Configure a non-empty model name for the selected provider.');
        }

        return $model;
    }
}
