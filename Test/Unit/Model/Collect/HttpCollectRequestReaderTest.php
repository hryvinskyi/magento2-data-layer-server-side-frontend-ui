<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideFrontendUi\Test\Unit\Model\Collect;

use Hryvinskyi\DataLayerServerSide\Model\Collect\CollectRequest;
use Hryvinskyi\DataLayerServerSide\Model\Collect\CollectRequestFactory;
use Hryvinskyi\DataLayerServerSideFrontendUi\Model\Collect\HttpCollectRequestReader;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use PHPUnit\Framework\TestCase;

/**
 * The request's headers, body and address are read as sent; a body declared too large is never read
 */
class HttpCollectRequestReaderTest extends TestCase
{
    /**
     * Every header, the body and the address come through as sent
     *
     * @return void
     */
    public function testRequestIsReadAsSent(): void
    {
        $request = $this->request([
            'Origin' => 'https://store.example',
            'Referer' => 'https://store.example/laptops.html',
            'Content-Type' => 'application/json',
            'Content-Length' => '13',
            'User-Agent' => 'Browser/1.0',
        ], '{"events":[]}');

        $read = $this->reader($request, '203.0.113.7')->read();

        self::assertSame('https://store.example', $read->getOrigin());
        self::assertSame('https://store.example/laptops.html', $read->getReferer());
        self::assertSame('application/json', $read->getContentType());
        self::assertSame('{"events":[]}', $read->getBody());
        self::assertSame('Browser/1.0', $read->getUserAgent());
        self::assertSame('203.0.113.7', $read->getRemoteAddress());
    }

    /**
     * A body declared larger than the collector takes is never read
     *
     * @return void
     */
    public function testOversizedBodyIsNotRead(): void
    {
        self::assertNull($this->reader($this->request(['Content-Length' => '16385'], null))->read()->getBody());
    }

    /**
     * Missing headers and an unknown address are unknown
     *
     * @return void
     */
    public function testMissingHeadersAreUnknown(): void
    {
        $read = $this->reader($this->request([], ''), false)->read();

        self::assertNull($read->getOrigin());
        self::assertNull($read->getContentType());
        self::assertNull($read->getUserAgent());
        self::assertNull($read->getRemoteAddress());
        self::assertSame('', $read->getBody());
    }

    /**
     * A request that is not an HTTP request reads as nothing
     *
     * @return void
     */
    public function testNonHttpRequestReadsAsNothing(): void
    {
        $read = $this->reader($this->createMock(RequestInterface::class))->read();

        self::assertNull($read->getOrigin());
        self::assertNull($read->getBody());
    }

    /**
     * Build a request carrying headers and a body
     *
     * @param array<string, string> $headers
     * @param string|null $body Null when the body must not be read
     * @return HttpRequest
     */
    private function request(array $headers, ?string $body): HttpRequest
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getHeader')->willReturnCallback(
            static fn (string $name): string|false => $headers[$name] ?? false
        );
        if ($body === null) {
            $request->expects(self::never())->method('getContent');
        } else {
            $request->method('getContent')->willReturn($body);
        }

        return $request;
    }

    /**
     * Build the reader
     *
     * @param RequestInterface $request
     * @param string|false $remoteAddress
     * @return HttpCollectRequestReader
     */
    private function reader(
        RequestInterface $request,
        string|false $remoteAddress = '203.0.113.7'
    ): HttpCollectRequestReader {
        $address = $this->createMock(RemoteAddress::class);
        $address->method('getRemoteAddress')->willReturn($remoteAddress);
        $factory = $this->createMock(CollectRequestFactory::class);
        $factory->method('create')->willReturnCallback(static function (array $data = []): CollectRequest {
            $text = static fn (string $key): ?string => is_string($data[$key] ?? null) ? $data[$key] : null;

            return new CollectRequest(
                $text('origin'),
                $text('referer'),
                $text('contentType'),
                $text('body'),
                $text('userAgent'),
                $text('remoteAddress')
            );
        });

        return new HttpCollectRequestReader($request, $address, $factory);
    }
}
