<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideFrontendUi\Model\Collect\Rule;

use Hryvinskyi\DataLayerServerSide\Api\Collect\CollectRequestRuleInterface;
use Hryvinskyi\DataLayerServerSide\Model\Collect\CollectRequest;

/**
 * Allows only a request whose body is declared as JSON
 *
 * A form or plain-text post is what a page elsewhere can send without the browser asking the store first; the
 * collector's own script always declares JSON. Parameters such as a charset are ignored.
 */
class JsonContentTypeRule implements CollectRequestRuleInterface
{
    private const MEDIA_TYPE = 'application/json';

    /**
     * @inheritDoc
     */
    public function allows(CollectRequest $request): bool
    {
        $contentType = $request->getContentType();
        if ($contentType === null) {
            return false;
        }

        return strtolower(trim(explode(';', $contentType, 2)[0])) === self::MEDIA_TYPE;
    }
}
