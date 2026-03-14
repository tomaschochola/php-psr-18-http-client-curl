<?php

declare(strict_types=1);

namespace TomasChochola\Psr\Http\Client;

use IteratorAggregate;
use NoDiscard;
use Override;
use Psr\Http\Client\ClientInterface;
use Traversable;

/**
 * @no-named-arguments
 *
 * @implements IteratorAggregate<mixed, mixed>
 */
readonly class HttpClientManifest implements IteratorAggregate
{
    #[NoDiscard]
    #[Override]
    public function getIterator(): Traversable
    {
        yield CurlHttpClient::class => [CurlHttpClientAssembler::class, 'assemble'];
        yield ClientInterface::class => [CurlHttpClientAssembler::class, 'assemble'];
    }
}
