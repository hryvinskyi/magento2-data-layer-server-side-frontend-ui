<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideFrontendUi\Plugin;

use Hryvinskyi\DataLayerServerSideFrontendUi\Api\Collect\CollectPathInterface;
use Hryvinskyi\DataLayerServerSideFrontendUi\Api\Collect\CollectRequestReaderInterface;
use Hryvinskyi\DataLayerServerSide\Api\Collect\CollectServiceInterface;
use Hryvinskyi\DataLayerServerSideFrontendUi\Api\Collect\StorefrontAreaInterface;
use Magento\Framework\App\Http;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Answers a POST to the collector's path before the application routes the request
 *
 * The front controller, the routers and the plugins and events of every storefront action each build sessions, so a
 * request that goes through them starts the PHP session; the collector runs once per page view and must not. Here
 * the request never reaches them: the storefront area is entered, the collector takes the request, and the answer
 * is `204 No Content` with `Cache-Control: no-store`, whatever happened. The answer is marked not cacheable, so the
 * page cache neither stores it nor touches the visitor's page-cache variation cookie. A failure is logged with its
 * class and message only.
 *
 * Every other request goes on unchanged after a method check and one string comparison of its path, which the
 * application computes for routing anyway; the collector's services are built only for the collector's requests.
 */
class AnswerCollectBeforeRouting
{
    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param CollectPathInterface $collectPath
     * @param StorefrontAreaInterface $storefrontArea
     * @param CollectRequestReaderInterface $requestReader
     * @param CollectServiceInterface $collector
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResponseInterface $response,
        private readonly CollectPathInterface $collectPath,
        private readonly StorefrontAreaInterface $storefrontArea,
        private readonly CollectRequestReaderInterface $requestReader,
        private readonly CollectServiceInterface $collector,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Answer a collector request, or launch the application for any other
     *
     * @param Http $subject
     * @param callable $proceed
     * @return ResponseInterface
     */
    public function aroundLaunch(Http $subject, callable $proceed): ResponseInterface
    {
        if (!$this->isCollectRequest()) {
            $response = $proceed();

            return $response instanceof ResponseInterface ? $response : $this->response;
        }

        try {
            $this->storefrontArea->enter();
            $this->collector->collect($this->requestReader->read());
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf('A request to the collector could not be taken: %s', $exception->getMessage()),
                ['exception_class' => get_class($exception)]
            );
        }

        return $this->noContent();
    }

    /**
     * Whether the request is a POST to the collector's path
     *
     * @return bool
     */
    private function isCollectRequest(): bool
    {
        return $this->request instanceof HttpRequest
            && $this->request->isPost()
            && $this->collectPath->matches((string) $this->request->getPathInfo());
    }

    /**
     * Turn the response into an empty answer that is never stored
     *
     * @return ResponseInterface
     */
    private function noContent(): ResponseInterface
    {
        if ($this->response instanceof HttpResponse) {
            $this->response->setHttpResponseCode(204);
            $this->response->setHeader('Cache-Control', 'no-store', true);
            $this->response->setBody('');
            $this->response->setMetadata('NotCacheable', true);
        }

        return $this->response;
    }
}
