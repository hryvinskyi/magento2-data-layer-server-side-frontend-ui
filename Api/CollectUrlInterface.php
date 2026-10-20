<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideFrontendUi\Api;

/**
 * The address a store's pages post the events their browser detects to, so the server can send them
 */
interface CollectUrlInterface
{
    /**
     * Get the store's own address of the collector
     *
     * The address is printed into pages the full-page cache keeps per store, so it never carries a session id.
     *
     * @param int $storeId
     * @return string|null Null when events are not sent from the server for the store's website
     */
    public function url(int $storeId): ?string;
}
