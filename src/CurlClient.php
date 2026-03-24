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

namespace TomasChochola\Psr\Http\Client;

use NoDiscard;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

use function curl_errno;
use function curl_error;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt_array;
use function in_array;
use function is_int;
use function is_string;
use function mb_strlen;
use function sscanf;

use const CURLINFO_RESPONSE_CODE;
use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_DEFAULT_PROTOCOL;
use const CURLOPT_HEADERFUNCTION;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_HTTP_VERSION;
use const CURLOPT_READFUNCTION;
use const CURLOPT_REQUEST_TARGET;
use const CURLOPT_UPLOAD;
use const CURLOPT_URL;
use const CURLOPT_WRITEFUNCTION;
use const CURL_HTTP_VERSION_1_0;
use const CURL_HTTP_VERSION_1_1;
use const CURL_HTTP_VERSION_2;
use const CURL_HTTP_VERSION_3;
use const CURL_HTTP_VERSION_NONE;

/**
 * @no-named-arguments
 */
readonly class CurlClient implements ClientInterface
{
    private readonly ResponseFactoryInterface $responseFactory;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    #[NoDiscard]
    #[Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $headers = [];

        foreach ($request->getHeaders() as $k => $v) {
            foreach ($v as $vv) {
                $headers[] = $k . ': ' . $vv;
            }
        }

        $curl = curl_init();

        if ($curl === false) {
            throw new NetworkException($request);
        }

        $response = $this->responseFactory->createResponse();
        $output = $response->getBody();
        $input = $request->getBody();
        $heads = [];

        if ($input->isSeekable()) {
            $input->rewind();
        }

        $method = $request->getMethod();

        $ok = curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_DEFAULT_PROTOCOL => 'https',
            CURLOPT_URL => (string) $request->getUri(),
            CURLOPT_REQUEST_TARGET => $request->getRequestTarget(),
            CURLOPT_HTTP_VERSION => match ($request->getProtocolVersion()) {
                '1', '1.0' => CURL_HTTP_VERSION_1_0,
                '1.1' => CURL_HTTP_VERSION_1_1,
                '2', '2.0' => CURL_HTTP_VERSION_2,
                '3', '3.0' => CURL_HTTP_VERSION_3,
                default => CURL_HTTP_VERSION_NONE,
            },
            CURLOPT_UPLOAD => !in_array($method, ['GET', 'HEAD', 'OPTIONS', 'TRACE'], true),
            CURLOPT_READFUNCTION => static fn(mixed $ch, mixed $fd, int $length): string => $input->read($length),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_WRITEFUNCTION => static fn(mixed $ch, string $data): int => $output->write($data),
            CURLOPT_HEADERFUNCTION => static function (mixed $ch, string $data) use (&$heads): int {
                $version = null;
                $status = null;
                $reason = null;
                $scanned = sscanf($data, " HTTP/ %f %d %[^\r\n]", $version, $status, $reason);

                if ($scanned === 2 || $scanned === 3) {
                    $heads = [];

                    return mb_strlen($data, '8bit');
                }

                $key = null;
                $val = null;
                $scanned = sscanf($data, " %[^:] : %[^\r\n]", $key, $val);

                if ($scanned === 2 && is_string($key) && is_string($val)) {
                    $heads[$key][] = $val;
                }

                return mb_strlen($data, '8bit');
            },
        ]);

        if ($ok !== true) {
            throw new NetworkException($request, curl_error($curl), curl_errno($curl));
        }

        $ok = curl_exec($curl);

        if ($ok !== true) {
            throw new NetworkException($request, curl_error($curl), curl_errno($curl));
        }

        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        if (!is_int($status)) {
            throw new NetworkException($request, curl_error($curl), curl_errno($curl));
        }

        $response = $response->withStatus($status);

        foreach ($heads as $k => $v) {
            foreach ($v as $vv) {
                $response = $response->withAddedHeader($k, $vv);
            }
        }

        return $response;
    }
}
