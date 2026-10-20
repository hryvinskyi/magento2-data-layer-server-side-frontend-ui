<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideFrontendUi\Model\Collect;

use Hryvinskyi\DataLayerServerSideFrontendUi\Api\Collect\StorefrontAreaInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use Magento\Framework\ObjectManagerInterface;

/**
 * Enters the storefront area the way the application does for a storefront request, before it routes one
 *
 * The area code is set and the storefront's object configuration is applied, so the objects built afterwards are
 * the ones a storefront request gets. Nothing of the area is loaded beyond that: no design, no translations.
 */
class StorefrontArea implements StorefrontAreaInterface
{
    /**
     * @param State $appState
     * @param ObjectManagerInterface $objectManager Receives the storefront's object configuration
     * @param ConfigLoaderInterface $configLoader
     */
    public function __construct(
        private readonly State $appState,
        private readonly ObjectManagerInterface $objectManager,
        private readonly ConfigLoaderInterface $configLoader
    ) {
    }

    /**
     * @inheritDoc
     */
    public function enter(): void
    {
        try {
            $this->appState->getAreaCode();

            return;
        } catch (LocalizedException) {
            $this->appState->setAreaCode(Area::AREA_FRONTEND);
            $this->objectManager->configure($this->configLoader->load(Area::AREA_FRONTEND));
        }
    }
}
