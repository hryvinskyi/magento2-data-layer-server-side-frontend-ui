<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideFrontendUi\Test\Unit\Model\Browser;

use Hryvinskyi\DataLayer\Api\KnownEventsInterface;
use Hryvinskyi\DataLayerApi\Api\Data\EventChannel;
use Hryvinskyi\DataLayerApi\Api\EventChannelPolicyInterface;
use Hryvinskyi\DataLayerServerSideFrontendUi\Api\CollectUrlInterface;
use Hryvinskyi\DataLayerServerSideFrontendUi\Model\Browser\ServerConfigProvider;
use PHPUnit\Framework\TestCase;

/**
 * With server-side delivery on for the store's website the browser receives every known event's channel and the
 * collector's address; with it off, nothing
 */
class ServerConfigProviderTest extends TestCase
{
    /**
     * Every known event gets the store view's channel, beside the collector's address
     *
     * @return void
     */
    public function testEnabledWebsiteGetsChannelsAndAddress(): void
    {
        $asked = [];
        $provider = new ServerConfigProvider(
            $this->collectUrl('https://shop.test/hryvinskyi_datalayer/collect/'),
            $this->knownEvents(),
            $this->channels($asked)
        );

        self::assertSame('server', $provider->getKey());
        self::assertSame(
            [
                'channels' => ['view_item' => 'browser', 'purchase' => 'server', 'select_item' => 'both'],
                'collectUrl' => 'https://shop.test/hryvinskyi_datalayer/collect/',
            ],
            $provider->provide(7)
        );
        self::assertSame([['view_item', 7], ['purchase', 7], ['select_item', 7]], $asked);
    }

    /**
     * Without server-side delivery the part is empty and no channel is looked up
     *
     * @return void
     */
    public function testDisabledWebsiteGetsNothing(): void
    {
        $asked = [];
        $provider = new ServerConfigProvider($this->collectUrl(null), $this->knownEvents(), $this->channels($asked));

        self::assertSame([], $provider->provide(7));
        self::assertSame([], $asked);
    }

    /**
     * Get a collector address that is the given one for store view 7
     *
     * @param string|null $url
     * @return CollectUrlInterface
     */
    private function collectUrl(?string $url): CollectUrlInterface
    {
        $collectUrl = $this->createMock(CollectUrlInterface::class);
        $collectUrl->method('url')->with(7)->willReturn($url);

        return $collectUrl;
    }

    /**
     * Get three known events
     *
     * @return KnownEventsInterface
     */
    private function knownEvents(): KnownEventsInterface
    {
        $known = $this->createMock(KnownEventsInterface::class);
        $known->method('getLabels')->willReturn([
            'view_item' => 'View item',
            'purchase' => 'Purchase',
            'select_item' => 'Select item',
        ]);

        return $known;
    }

    /**
     * Get a channel policy that records what it is asked
     *
     * @param list<array{string, int|null}> $asked
     * @return EventChannelPolicyInterface
     */
    private function channels(array &$asked): EventChannelPolicyInterface
    {
        $policy = $this->createMock(EventChannelPolicyInterface::class);
        $policy->method('channelOf')->willReturnCallback(
            static function (string $eventName, ?int $storeId = null) use (&$asked): EventChannel {
                $asked[] = [$eventName, $storeId];

                return match ($eventName) {
                    'purchase' => EventChannel::Server,
                    'select_item' => EventChannel::Both,
                    default => EventChannel::Browser,
                };
            }
        );

        return $policy;
    }
}
