<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideFrontendUi\Test\Integration;

use Hryvinskyi\DataLayerFrontendUi\ViewModel\Bootstrap;
use Hryvinskyi\DataLayerServerSide\Test\Integration\ResolvesServices;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\View\Layout;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap as TestBootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The page's browser settings carry the server part, every known event's channel and the collector's address,
 * exactly while events are sent from the server for the store's website
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class BrowserConfigTest extends TestCase
{
    use ResolvesServices;

    /**
     * With server-side delivery on, the bootstrap prints the core part and the server part
     *
     * @magentoConfigFixture base_website hryvinskyi_datalayer_server/general/enabled 1
     * @magentoConfigFixture current_store hryvinskyi_datalayer/general/enabled 1
     * @return void
     */
    public function testEnabledWebsitePrintsTheServerPart(): void
    {
        $this->service(MutableScopeConfigInterface::class)->setValue(
            'hryvinskyi_datalayer/channels/routing',
            '{"r1":{"event":"view_item_list","channel":"server"},"r2":{"event":"select_item","channel":"both"}}',
            ScopeInterface::SCOPE_STORE,
            'default'
        );

        $config = $this->printedConfig();

        self::assertIsArray($config['core'] ?? null);
        $server = $config['server'] ?? null;
        self::assertIsArray($server);
        self::assertIsString($server['collectUrl'] ?? null);
        self::assertStringEndsWith('/hryvinskyi_datalayer/collect/', $server['collectUrl']);
        $channels = $server['channels'] ?? null;
        self::assertIsArray($channels);
        self::assertSame('server', $channels['view_item_list'] ?? null);
        self::assertSame('both', $channels['select_item'] ?? null);
        self::assertSame('browser', $channels['view_item'] ?? null);
        self::assertArrayHasKey('add_to_cart', $channels);
    }

    /**
     * With server-side delivery off, the page carries the core part only
     *
     * @magentoConfigFixture current_store hryvinskyi_datalayer/general/enabled 1
     * @return void
     */
    public function testDisabledWebsitePrintsNoServerPart(): void
    {
        $config = $this->printedConfig();

        self::assertIsArray($config['core'] ?? null);
        self::assertArrayNotHasKey('server', $config);
    }

    /**
     * Render the default page in full and read the settings it prints
     *
     * @return array<mixed>
     */
    private function printedConfig(): array
    {
        $page = $this->service(PageFactory::class)->create();
        $layout = $page->getLayout();
        self::assertInstanceOf(Layout::class, $layout);
        $layout->getUpdate()->addHandle(['default']);
        $page->getConfig()->setPageLayout('1column');
        $response = TestBootstrap::getObjectManager()->create(HttpResponse::class);
        self::assertInstanceOf(HttpResponse::class, $response);
        $page->renderResult($response);

        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $html = $response->getContent();
        self::assertIsString($html);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $script = $document->getElementById(Bootstrap::CONFIG_ID);
        self::assertInstanceOf(\DOMElement::class, $script, 'The browser reads its settings by this id.');
        self::assertSame('application/json', $script->getAttribute('type'));
        $config = json_decode($script->textContent, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($config);

        return $config;
    }
}
