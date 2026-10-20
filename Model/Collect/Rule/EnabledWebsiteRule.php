<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideFrontendUi\Model\Collect\Rule;

use Hryvinskyi\DataLayerServerSide\Api\Collect\CollectRequestRuleInterface;
use Hryvinskyi\DataLayerServerSide\Api\ConfigInterface;
use Hryvinskyi\DataLayerServerSide\Model\Collect\CollectRequest;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Allows a request only while events are sent from the server for the current store's website
 */
class EnabledWebsiteRule implements CollectRequestRuleInterface
{
    /**
     * @param ConfigInterface $config
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritDoc
     */
    public function allows(CollectRequest $request): bool
    {
        return $this->config->isEnabled((int) $this->storeManager->getStore()->getWebsiteId());
    }
}
