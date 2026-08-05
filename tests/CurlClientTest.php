<?php

/**
 * @author Tomáš Chochola <tomaschochola@tomaschochola.cz>
 * @copyright © 2026 Tomáš Chochola <tomaschochola@tomaschochola.cz>
 *
 * @license CC-BY-ND-4.0
 *
 * @see {@link https://creativecommons.org/licenses/by-nd/4.0/} License
 * @see {@link https://github.com/tomaschochola} GitHub Profile
 * @see {@link https://github.com/sponsors/tomaschochola} GitHub Sponsors
 */

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use TomasChochola\Psr\Http\Client\CurlClient;
use TomasChochola\Psr\Http\Client\NetworkException;
use TomasChochola\Psr\Http\Factory\RequestFactory;
use TomasChochola\Psr\Http\Factory\ResponseFactory;
use TomasChochola\Psr\Http\Factory\StreamFactory;
use TomasChochola\Psr\Http\Factory\UriFactory;

use function fclose;
use function fwrite;
use function is_resource;
use function mb_strlen;
use function mb_strrchr;
use function pcntl_fork;
use function pcntl_waitpid;
use function pcntl_wexitstatus;
use function stream_socket_accept;
use function stream_socket_get_name;
use function stream_socket_server;

/**
 * @internal
 *
 * @no-named-arguments
 */
#[CoversClass(CurlClient::class)]
#[Medium()]
final class CurlClientTest extends TestCase
{
    #[Test()]
    public function reportsNetworkFailuresWithOriginalRequest(): void
    {
        $streamFactory = new StreamFactory();
        $uriFactory = new UriFactory();
        $request = (new RequestFactory($streamFactory, $uriFactory))->createRequest('GET', 'http://127.0.0.1:1/unreachable');
        $client = new CurlClient(new ResponseFactory($streamFactory));

        try {
            self::fail('Network request unexpectedly succeeded: ' . $client->sendRequest($request)::class);
        } catch (NetworkException $exception) {
            self::assertSame($request, $exception->getRequest());
            self::assertNotSame('', $exception->getMessage());
            self::assertGreaterThan(0, $exception->getCode());
        }
    }

    #[Test()]
    public function sendsRequestAndCreatesResponseFromLocalHttpServer(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($server);
        $address = stream_socket_get_name($server, false);
        self::assertIsString($address);
        $port = mb_strrchr($address, ':', false);
        self::assertIsString($port);
        $pid = pcntl_fork();
        self::assertNotSame(-1, $pid);

        if ($pid === 0) {
            $connection = stream_socket_accept($server, 5);

            if (is_resource($connection)) {
                $message = "HTTP/1.1 201 Created\r\nX-Test: first\r\nX-Test: second\r\nContent-Length: 7\r\nConnection: close\r\n\r\npayload";
                $written = fwrite($connection, $message);
                fclose($connection);

                if ($written !== mb_strlen($message, '8bit')) {
                    exit(1);
                }
            }

            fclose($server);

            exit(0);
        }

        fclose($server);

        $streamFactory = new StreamFactory();
        $uriFactory = new UriFactory();
        $request = (new RequestFactory($streamFactory, $uriFactory))->createRequest('GET', 'http://127.0.0.1' . $port . '/resource');
        $response = (new CurlClient(new ResponseFactory($streamFactory)))->sendRequest($request);
        $waited = pcntl_waitpid($pid, $status);

        self::assertSame($pid, $waited);
        self::assertIsInt($status);
        self::assertSame(0, pcntl_wexitstatus($status));
        self::assertSame(201, $response->getStatusCode());
        self::assertSame('Created', $response->getReasonPhrase());
        self::assertSame('1.1', $response->getProtocolVersion());
        self::assertSame(['first', 'second'], $response->getHeader('X-Test'));
        self::assertSame('payload', (string) $response->getBody());
    }
}
