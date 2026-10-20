<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideFrontendUi\Test\Unit\Model\Collect;

use Hryvinskyi\DataLayerServerSideFrontendUi\Model\Collect\CollectPath;
use Hryvinskyi\DataLayerServerSideFrontendUi\Model\Collect\StorefrontArea;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Phrase;
use PHPUnit\Framework\TestCase;

/**
 * A request answered before routing enters the storefront area as routing would; the collector's path is one string
 */
class StorefrontAreaTest extends TestCase
{
    /**
     * Without an area, the storefront's code is set and its object configuration applied
     *
     * @return void
     */
    public function testStorefrontIsEnteredWhenNoAreaIsSet(): void
    {
        $state = $this->createMock(State::class);
        $state->method('getAreaCode')->willThrowException(new LocalizedException(new Phrase('Area code is not set')));
        $state->expects(self::once())->method('setAreaCode')->with('frontend');
        $configLoader = $this->createMock(ConfigLoaderInterface::class);
        $configLoader->expects(self::once())->method('load')->with('frontend')->willReturn(['preferences' => []]);
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->expects(self::once())->method('configure')->with(['preferences' => []]);

        (new StorefrontArea($state, $objectManager, $configLoader))->enter();
    }

    /**
     * A request already in an area keeps it
     *
     * @return void
     */
    public function testAreaAlreadySetIsKept(): void
    {
        $state = $this->createMock(State::class);
        $state->method('getAreaCode')->willReturn('frontend');
        $state->expects(self::never())->method('setAreaCode');
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->expects(self::never())->method('configure');

        (new StorefrontArea($state, $objectManager, $this->createMock(ConfigLoaderInterface::class)))->enter();
    }

    /**
     * The collector's path matches with or without slashes, and nothing below or beside it does
     *
     * @return void
     */
    public function testCollectorPath(): void
    {
        $path = new CollectPath();

        self::assertSame('hryvinskyi_datalayer/collect', $path->getPath());
        self::assertTrue($path->matches('/hryvinskyi_datalayer/collect/'));
        self::assertTrue($path->matches('/hryvinskyi_datalayer/collect'));
        self::assertFalse($path->matches('/hryvinskyi_datalayer/collect/index'));
        self::assertFalse($path->matches('/hryvinskyi_datalayer/collector'));
        self::assertFalse($path->matches('/default/hryvinskyi_datalayer/collect'));
        self::assertFalse($path->matches('/'));
    }
}
