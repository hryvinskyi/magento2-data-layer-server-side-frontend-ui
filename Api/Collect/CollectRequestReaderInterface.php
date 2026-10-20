<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideFrontendUi\Api\Collect;

use Hryvinskyi\DataLayerServerSide\Model\Collect\CollectRequest;

/**
 * Reads what the collector needs from the current HTTP request
 */
interface CollectRequestReaderInterface
{
    /**
     * Read the current request
     *
     * A body declared larger than the collector takes is never read.
     *
     * @return CollectRequest
     */
    public function read(): CollectRequest;
}
