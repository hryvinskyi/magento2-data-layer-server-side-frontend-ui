<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideFrontendUi\Model\Collect\Rule;

use Hryvinskyi\DataLayerFrontendUi\Api\Browser\StoreOriginInterface;
use Hryvinskyi\DataLayerServerSide\Api\Collect\CollectRequestRuleInterface;
use Hryvinskyi\DataLayerServerSide\Model\Collect\CollectRequest;

/**
 * Allows only a request posted from one of the store's own pages
 *
 * The data layer's store-origin rule decides, the same one its other storefront endpoint applies: the `Origin`
 * header, or without one the `Referer`, must name the host of the store's secure or unsecure base URL. A browser
 * always sends `Origin` on a cross-site POST, so a page elsewhere cannot post events in a visitor's name.
 */
class SameOriginRule implements CollectRequestRuleInterface
{
    /**
     * @param StoreOriginInterface $storeOrigin
     */
    public function __construct(
        private readonly StoreOriginInterface $storeOrigin
    ) {
    }

    /**
     * @inheritDoc
     */
    public function allows(CollectRequest $request): bool
    {
        return $this->storeOrigin->isStoreOrigin($request->getOrigin(), $request->getReferer());
    }
}
