<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideFrontendUi\Model;

use Hryvinskyi\DataLayerServerSideFrontendUi\Api\Collect\CollectPathInterface;
use Hryvinskyi\DataLayerServerSideFrontendUi\Api\CollectUrlInterface;
use Hryvinskyi\DataLayerServerSide\Api\ConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Builds a store's address of the collector while events are sent from the server for its website
 *
 * The address is the store's URL of the collector's path, store code and base URL path included, so a request to it
 * reaches the collector however the store's URLs are set up.
 */
class CollectUrl implements CollectUrlInterface
{
    /**
     * @param ConfigInterface $config
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $url Builds storefront URLs
     * @param CollectPathInterface $collectPath
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $url,
        private readonly CollectPathInterface $collectPath
    ) {
    }

    /**
     * @inheritDoc
     */
    public function url(int $storeId): ?string
    {
        $websiteId = (int) $this->storeManager->getStore($storeId)->getWebsiteId();
        if (!$this->config->isEnabled($websiteId)) {
            return null;
        }

        return $this->url->getUrl($this->collectPath->getPath(), ['_scope' => $storeId, '_nosid' => true]);
    }
}
