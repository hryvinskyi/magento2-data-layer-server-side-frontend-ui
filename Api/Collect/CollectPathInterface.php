<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideFrontendUi\Api\Collect;

/**
 * Where on a store the collector listens
 *
 * The collector is no storefront route: a request to its path is answered before the application routes it.
 */
interface CollectPathInterface
{
    /**
     * Get the path the store's collector address is built from, relative to the store's base URL
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * Whether a request path is the collector's
     *
     * @param string $pathInfo The request path, without the store's base URL path and store code
     * @return bool
     */
    public function matches(string $pathInfo): bool;
}
