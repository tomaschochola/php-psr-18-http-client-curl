<?php

declare(strict_types=1);

namespace TomasChochola\Psr\Http\Client;

use NoDiscard;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

use function assert;

/**
 * @no-named-arguments
 */
readonly class CurlHttpClientAssembler
{
    #[NoDiscard]
    public static function assemble(ContainerInterface $container): CurlHttpClient
    {
        $responseFactory = $container->get(ResponseFactoryInterface::class);

        assert($responseFactory instanceof ResponseFactoryInterface);

        return new CurlHttpClient($responseFactory);
    }
}
