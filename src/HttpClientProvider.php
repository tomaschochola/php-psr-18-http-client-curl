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
readonly class HttpClientProvider implements IteratorAggregate
{
    #[NoDiscard]
    #[Override]
    public function getIterator(): Traversable
    {
        yield CurlHttpClient::class => [CurlHttpClient::class, 'unload'];
        yield ClientInterface::class => [CurlHttpClient::class, 'unload'];
    }
}
