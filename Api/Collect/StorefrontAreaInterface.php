<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideFrontendUi\Api\Collect;

/**
 * Puts a request answered before routing into the storefront area, as routing would have
 */
interface StorefrontAreaInterface
{
    /**
     * Enter the storefront area, unless the request is already in an area
     *
     * @return void
     */
    public function enter(): void;
}
