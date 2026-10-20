<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideFrontendUi\Model\Collect\Rule;

use Hryvinskyi\DataLayerServerSide\Api\Collect\CollectRequestRuleInterface;
use Hryvinskyi\DataLayerServerSide\Api\Collect\CollectServiceInterface;
use Hryvinskyi\DataLayerServerSide\Model\Collect\CollectRequest;

/**
 * Allows only a request with a body, of at most the size the collector takes
 */
class BodySizeRule implements CollectRequestRuleInterface
{
    /**
     * @inheritDoc
     */
    public function allows(CollectRequest $request): bool
    {
        $body = $request->getBody();

        return $body !== null && $body !== '' && strlen($body) <= CollectServiceInterface::MAX_BODY_BYTES;
    }
}
