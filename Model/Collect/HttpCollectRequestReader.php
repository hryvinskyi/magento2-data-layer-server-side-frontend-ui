<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideFrontendUi\Model\Collect;

use Hryvinskyi\DataLayerServerSide\Api\Collect\CollectServiceInterface;
use Hryvinskyi\DataLayerServerSide\Model\Collect\CollectRequest;
use Hryvinskyi\DataLayerServerSide\Model\Collect\CollectRequestFactory;
use Hryvinskyi\DataLayerServerSideFrontendUi\Api\Collect\CollectRequestReaderInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\HTTP\PhpEnvironment\Request;

/**
 * Reads the headers, body and address of the current HTTP request
 *
 * A request that declares a body larger than the collector takes keeps its body unread, so a large post costs no
 * memory. A header the request does not carry, or an address that cannot be told, is unknown.
 */
class HttpCollectRequestReader implements CollectRequestReaderInterface
{
    /**
     * @param RequestInterface $request
     * @param RemoteAddress $remoteAddress
     * @param CollectRequestFactory $collectRequestFactory
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RemoteAddress $remoteAddress,
        private readonly CollectRequestFactory $collectRequestFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function read(): CollectRequest
    {
        if (!$this->request instanceof Request) {
            return $this->collectRequestFactory->create();
        }
        $declaredLength = $this->header($this->request, 'Content-Length');
        $tooLarge = $declaredLength !== null && (int) $declaredLength > CollectServiceInterface::MAX_BODY_BYTES;
        $remoteAddress = (string) $this->remoteAddress->getRemoteAddress();

        return $this->collectRequestFactory->create([
            'origin' => $this->header($this->request, 'Origin'),
            'referer' => $this->header($this->request, 'Referer'),
            'contentType' => $this->header($this->request, 'Content-Type'),
            'body' => $tooLarge ? null : (string) $this->request->getContent(),
            'userAgent' => $this->header($this->request, 'User-Agent'),
            'remoteAddress' => $remoteAddress !== '' ? $remoteAddress : null,
        ]);
    }

    /**
     * Read a request header
     *
     * @param Request $request
     * @param string $name
     * @return string|null Null when the request does not carry it
     */
    private function header(Request $request, string $name): ?string
    {
        $value = $request->getHeader($name);

        return is_string($value) ? $value : null;
    }
}
