<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideFrontendUi\Test\Unit\Plugin;

use Hryvinskyi\DataLayerServerSideFrontendUi\Api\Collect\CollectRequestReaderInterface;
use Hryvinskyi\DataLayerServerSide\Api\Collect\CollectServiceInterface;
use Hryvinskyi\DataLayerServerSideFrontendUi\Api\Collect\StorefrontAreaInterface;
use Hryvinskyi\DataLayerServerSideFrontendUi\Model\Collect\CollectPath;
use Hryvinskyi\DataLayerServerSide\Model\Collect\CollectRequest;
use Hryvinskyi\DataLayerServerSideFrontendUi\Plugin\AnswerCollectBeforeRouting;
use Hryvinskyi\DataLayerServerSide\Test\Unit\Fake\RecordingLogger;
use Magento\Framework\App\Http;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ResponseInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A POST to the collector's path is answered 204 before routing; every other request is launched untouched
 */
class AnswerCollectBeforeRoutingTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $calls = [];

    /**
     * @var array<string, mixed>
     */
    private array $answer = [];

    /**
     * @var RecordingLogger
     */
    private RecordingLogger $logger;

    /**
     * @var HttpResponse
     */
    private HttpResponse $response;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
        $this->response = $this->createMock(HttpResponse::class);
        $this->response->method('setHttpResponseCode')->willReturnCallback(function (mixed $code) {
            $this->answer['code'] = $code;

            return $this->response;
        });
        $this->response->method('setHeader')->willReturnCallback(function (mixed $name, mixed $value) {
            $this->answer[is_string($name) ? $name : ''] = $value;

            return $this->response;
        });
        $this->response->method('setBody')->willReturnCallback(function (mixed $body) {
            $this->answer['body'] = $body;

            return $this->response;
        });
        $this->response->method('setMetadata')->willReturnCallback(function (mixed $key, mixed $value) {
            $this->answer['metadata:' . (is_string($key) ? $key : '')] = $value;

            return $this->response;
        });
    }

    /**
     * Any request but a POST to the collector's path is launched, and no collector service is used
     *
     * @param string $method
     * @param string $pathInfo
     * @return void
     */
    #[DataProvider('otherRequests')]
    public function testOtherRequestIsLaunched(string $method, string $pathInfo): void
    {
        $launched = $this->createMock(ResponseInterface::class);

        $result = $this->plugin($this->request($method, $pathInfo))->aroundLaunch(
            $this->createMock(Http::class),
            function () use ($launched): ResponseInterface {
                $this->calls[] = 'launch';

                return $launched;
            }
        );

        self::assertSame($launched, $result);
        self::assertSame(['launch'], $this->calls);
        self::assertSame([], $this->answer);
    }

    /**
     * Requests the collector leaves alone
     *
     * @return array<string, array{string, string}>
     */
    public static function otherRequests(): array
    {
        return [
            'a page view' => ['GET', '/laptops.html'],
            'a GET of the collector\'s path' => ['GET', '/hryvinskyi_datalayer/collect/'],
            'a POST elsewhere' => ['POST', '/checkout/cart/add/'],
            'a POST below the collector\'s path' => ['POST', '/hryvinskyi_datalayer/collect/elsewhere'],
        ];
    }

    /**
     * A request that is not an HTTP request is launched
     *
     * @return void
     */
    public function testNonHttpRequestIsLaunched(): void
    {
        $launched = $this->createMock(ResponseInterface::class);

        $result = $this->plugin($this->createMock(RequestInterface::class))->aroundLaunch(
            $this->createMock(Http::class),
            static fn (): ResponseInterface => $launched
        );

        self::assertSame($launched, $result);
    }

    /**
     * A POST to the collector's path enters the storefront, is collected and answered 204, never routed
     *
     * @param string $pathInfo
     * @return void
     */
    #[DataProvider('collectorPaths')]
    public function testCollectorRequestIsAnsweredWithNoContent(string $pathInfo): void
    {
        $result = $this->plugin($this->request('POST', $pathInfo))->aroundLaunch(
            $this->createMock(Http::class),
            function (): ResponseInterface {
                $this->calls[] = 'launch';

                return $this->response;
            }
        );

        self::assertSame($this->response, $result);
        self::assertSame(['enter', 'read', 'collect'], $this->calls);
        self::assertSame(
            ['code' => 204, 'Cache-Control' => 'no-store', 'body' => '', 'metadata:NotCacheable' => true],
            $this->answer
        );
    }

    /**
     * Paths of the collector
     *
     * @return array<string, array{string}>
     */
    public static function collectorPaths(): array
    {
        return [
            'with a trailing slash' => ['/hryvinskyi_datalayer/collect/'],
            'without one' => ['/hryvinskyi_datalayer/collect'],
        ];
    }

    /**
     * A failure is logged with its class and message and still answered 204
     *
     * @return void
     */
    public function testFailureIsAnsweredWithNoContent(): void
    {
        $result = $this->plugin($this->request('POST', '/hryvinskyi_datalayer/collect/'), true)->aroundLaunch(
            $this->createMock(Http::class),
            fn (): ResponseInterface => $this->response
        );

        self::assertSame($this->response, $result);
        self::assertSame(204, $this->answer['code'] ?? null);
        self::assertCount(1, $this->logger->records);
        self::assertStringContainsString('cannot read', $this->logger->records[0]['message']);
        self::assertSame(['exception_class' => \RuntimeException::class], $this->logger->records[0]['context']);
    }

    /**
     * Build a request
     *
     * @param string $method
     * @param string $pathInfo
     * @return HttpRequest
     */
    private function request(string $method, string $pathInfo): HttpRequest
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('isPost')->willReturn($method === 'POST');
        $request->method('getPathInfo')->willReturn($pathInfo);

        return $request;
    }

    /**
     * Build the plugin with recording collaborators
     *
     * @param RequestInterface $request
     * @param bool $failingReader Whether reading the request throws
     * @return AnswerCollectBeforeRouting
     */
    private function plugin(RequestInterface $request, bool $failingReader = false): AnswerCollectBeforeRouting
    {
        $area = $this->createMock(StorefrontAreaInterface::class);
        $area->method('enter')->willReturnCallback(function (): void {
            $this->calls[] = 'enter';
        });
        $reader = $this->createMock(CollectRequestReaderInterface::class);
        $reader->method('read')->willReturnCallback(function () use ($failingReader): CollectRequest {
            if ($failingReader) {
                throw new \RuntimeException('cannot read the body');
            }
            $this->calls[] = 'read';

            return new CollectRequest();
        });
        $collector = $this->createMock(CollectServiceInterface::class);
        $collector->method('collect')->willReturnCallback(function (): void {
            $this->calls[] = 'collect';
        });

        return new AnswerCollectBeforeRouting(
            $request,
            $this->response,
            new CollectPath(),
            $area,
            $reader,
            $collector,
            $this->logger
        );
    }
}
