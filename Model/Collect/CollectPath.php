<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideFrontendUi\Model\Collect;

use Hryvinskyi\DataLayerServerSideFrontendUi\Api\Collect\CollectPathInterface;

/**
 * The collector listens at `hryvinskyi_datalayer/collect`, with or without a trailing slash
 *
 * Matching is one string comparison, because every request of the store is asked.
 */
class CollectPath implements CollectPathInterface
{
    private const PATH = 'hryvinskyi_datalayer/collect';

    /**
     * @inheritDoc
     */
    public function getPath(): string
    {
        return self::PATH;
    }

    /**
     * @inheritDoc
     */
    public function matches(string $pathInfo): bool
    {
        return trim($pathInfo, '/') === self::PATH;
    }
}
