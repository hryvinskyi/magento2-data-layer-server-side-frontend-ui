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
 * Refuses a request from a crawler that runs the page's scripts, and one that names no user agent at all
 *
 * Events sent from the server are not filtered for bots the way the browser tag's hits are, so a crawler's events
 * would reach the reports as visits. A user agent is ignored when it contains, in any case, one of the
 * `ignoredUserAgents` fragments.
 */
class IgnoredUserAgentRule implements CollectRequestRuleInterface
{
    /**
     * @var list<string>
     */
    private readonly array $ignoredUserAgents;

    /**
     * @param array<mixed> $ignoredUserAgents User agent fragments, matched in any case
     * @throws \InvalidArgumentException When a fragment is not a non-empty string
     */
    public function __construct(array $ignoredUserAgents = [])
    {
        $accepted = [];
        foreach ($ignoredUserAgents as $name => $fragment) {
            if (!is_string($fragment) || trim($fragment) === '') {
                throw new \InvalidArgumentException(
                    sprintf('The ignored user agent "%s" must be a non-empty text.', (string) $name)
                );
            }
            $accepted[] = strtolower(trim($fragment));
        }
        $this->ignoredUserAgents = $accepted;
    }

    /**
     * @inheritDoc
     */
    public function allows(CollectRequest $request): bool
    {
        $userAgent = strtolower(trim((string) $request->getUserAgent()));
        if ($userAgent === '') {
            return false;
        }
        foreach ($this->ignoredUserAgents as $fragment) {
            if (str_contains($userAgent, $fragment)) {
                return false;
            }
        }

        return true;
    }
}
