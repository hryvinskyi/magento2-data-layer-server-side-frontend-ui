<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideFrontendUi\Model\Browser;

use Hryvinskyi\DataLayer\Api\KnownEventsInterface;
use Hryvinskyi\DataLayerApi\Api\BrowserConfigProviderInterface;
use Hryvinskyi\DataLayerApi\Api\EventChannelPolicyInterface;
use Hryvinskyi\DataLayerServerSideFrontendUi\Api\CollectUrlInterface;

/**
 * Tells the browser module, under `server`, where each event goes and where to post the events meant for the server
 *
 * `channels` gives every known event its store view channel (`browser`, `server` or `both`); `collectUrl` is the
 * store's own address of the collector. Both are store-level settings, safe in a cached page. While events are not
 * sent from the server for the store's website the part is empty, so the page carries no `server` key and the browser
 * pushes every event itself.
 */
class ServerConfigProvider implements BrowserConfigProviderInterface
{
    /**
     * @param CollectUrlInterface $collectUrl
     * @param KnownEventsInterface $knownEvents
     * @param EventChannelPolicyInterface $channelPolicy
     */
    public function __construct(
        private readonly CollectUrlInterface $collectUrl,
        private readonly KnownEventsInterface $knownEvents,
        private readonly EventChannelPolicyInterface $channelPolicy
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return 'server';
    }

    /**
     * @inheritDoc
     *
     * @return array{channels?: array<string, string>, collectUrl?: string}
     */
    public function provide(int $storeId): array
    {
        $collectUrl = $this->collectUrl->url($storeId);
        if ($collectUrl === null) {
            return [];
        }

        $channels = [];
        foreach (array_keys($this->knownEvents->getLabels()) as $eventName) {
            $channels[$eventName] = $this->channelPolicy->channelOf($eventName, $storeId)->value;
        }

        return ['channels' => $channels, 'collectUrl' => $collectUrl];
    }
}
