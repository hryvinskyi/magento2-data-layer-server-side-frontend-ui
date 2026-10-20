<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideFrontendUi\Test\Unit\Model;

use Hryvinskyi\DataLayerServerSideFrontendUi\Model\Collect\CollectPath;
use Hryvinskyi\DataLayerServerSideFrontendUi\Model\CollectUrl;
use Hryvinskyi\DataLayerServerSide\Test\Unit\Fake\BuildsCollaborators;
use Hryvinskyi\DataLayerServerSide\Test\Unit\Fake\FakeConfig;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\TestCase;

/**
 * A store's collector address exists only while its website sends events from the server, and carries no session id
 */
class CollectUrlTest extends TestCase
{
    use BuildsCollaborators;

    /**
     * The address is built in the store's scope, without a session id
     *
     * @return void
     */
    public function testEnabledWebsiteGetsTheStoreAddress(): void
    {
        $url = $this->createMock(UrlInterface::class);
        $url->expects(self::once())->method('getUrl')
            ->with('hryvinskyi_datalayer/collect', ['_scope' => 5, '_nosid' => true])
            ->willReturn('https://store.example/hryvinskyi_datalayer/collect/');

        $collectUrl = new CollectUrl(new FakeConfig([2]), $this->storeManager([5 => 2]), $url, new CollectPath());

        self::assertSame('https://store.example/hryvinskyi_datalayer/collect/', $collectUrl->url(5));
    }

    /**
     * A store of a website without server-side delivery has no address
     *
     * @return void
     */
    public function testDisabledWebsiteGetsNone(): void
    {
        $url = $this->createMock(UrlInterface::class);
        $url->expects(self::never())->method('getUrl');

        $collectUrl = new CollectUrl(new FakeConfig([1]), $this->storeManager([5 => 2]), $url, new CollectPath());

        self::assertNull($collectUrl->url(5));
    }
}
