<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests;

use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\Test\HttpClientTestCase as BaseHttpClientTestCase;

abstract class HttpClientTestCase extends BaseHttpClientTestCase
{
    public function testAcceptHeader()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $response = $client->request('GET', 'http://localhost:8057');
        $requestHeaders = $response->toArray();

        $this->assertSame('*/*', $requestHeaders['HTTP_ACCEPT']);

        $response = $client->request('GET', 'http://localhost:8057', [
            'headers' => [
                'Accept' => 'foo/bar',
            ],
        ]);
        $requestHeaders = $response->toArray();

        $this->assertSame('foo/bar', $requestHeaders['HTTP_ACCEPT']);

        $response = $client->request('GET', 'http://localhost:8057', [
            'headers' => [
                'Accept' => null,
            ],
        ]);
        $requestHeaders = $response->toArray();

        $this->assertArrayNotHasKey('HTTP_ACCEPT', $requestHeaders);
    }

    public function testToStream()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057');
        $stream = $response->toStream();

        $this->assertSame("{\n    \"SER", fread($stream, 10));
        $this->assertSame('VER_PROTOCOL', fread($stream, 12));
        $this->assertFalse(feof($stream));
        $this->assertTrue(rewind($stream));

        $this->assertIsArray(json_decode(fread($stream, 1024), true));
        $this->assertSame('', fread($stream, 1));
        $this->assertTrue(feof($stream));
    }

    public function testToStream404()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/404');
        $stream = $response->toStream(false);

        $this->assertSame("{\n    \"SER", fread($stream, 10));
        $this->assertSame('VER_PROTOCOL', fread($stream, 12));
        $this->assertSame($response, stream_get_meta_data($stream)['wrapper_data']->getResponse());
        $this->assertSame(404, $response->getStatusCode());

        $this->expectException(ClientException::class);
        $response = $client->request('GET', 'http://localhost:8057/404');
        $stream = $response->toStream();
    }

    public function testNonBlockingStream()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/timeout-body');
        $stream = $response->toStream();

        $this->assertTrue(stream_set_blocking($stream, false));
        $this->assertSame('<1>', fread($stream, 8192));
        $this->assertFalse(feof($stream));

        $this->assertTrue(stream_set_blocking($stream, true));
        $this->assertSame('<2>', fread($stream, 8192));
        $this->assertSame('', fread($stream, 8192));
        $this->assertTrue(feof($stream));
    }

    public function testTimeoutIsNotAFatalError()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057/timeout-body', [
            'timeout' => 0.1,
        ]);

        try {
            $response->getContent();
            $this->fail(TransportException::class.' expected');
        } catch (TransportException $e) {
        }

        usleep(400000);
        $this->assertSame('<1><2>', $response->getContent());
    }
}
