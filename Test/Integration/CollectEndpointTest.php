<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideFrontendUi\Test\Integration;

use Hryvinskyi\DataLayer\Api\KnownEventsInterface;
use Hryvinskyi\DataLayerApi\Api\EventAvailabilityInterface;
use Hryvinskyi\DataLayerApi\Api\EventChannelPolicyInterface;
use Hryvinskyi\DataLayerServerSide\Api\Collect\CollectServiceInterface;
use Hryvinskyi\DataLayerServerSide\Test\Integration\ReadsTables;
use Hryvinskyi\DataLayerServerSide\Test\Integration\ResolvesServices;
use Hryvinskyi\DataLayerServerSideFrontendUi\Api\CollectUrlInterface;
use Laminas\Http\Header\HeaderInterface;
use Laminas\Http\Headers;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\Session\SessionStartChecker;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\Request;
use Magento\TestFramework\Response;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * Browser events posted to the collector reach the outbox once each, and only from the store's own pages
 *
 * Every request is posted through the storefront application exactly as a browser's beacon arrives: JSON body,
 * `Origin` header, analytics cookie. A POST to the collector's path is answered before routing with `204 No Content`,
 * starts no session and writes no visitor row; every other request is routed as before.
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class CollectEndpointTest extends AbstractController
{
    use ResolvesServices;
    use ReadsTables;

    private const OUTBOX = 'hryvinskyi_datalayer_server_event';
    private const CLIENT_ID = '2020202020.1700000000';
    private const VARY_COOKIE = 'X-Magento-Vary';
    private const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/130.0 Safari/537.36';

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $_COOKIE['_ga'] = 'GA1.1.' . self::CLIENT_ID;
        $this->service(MutableScopeConfigInterface::class)->setValue(
            'hryvinskyi_datalayer/channels/routing',
            '{"r1":{"event":"view_item_list","channel":"server"},"r2":{"event":"select_item","channel":"server"},'
            . '"r3":{"event":"purchase","channel":"server"}}',
            ScopeInterface::SCOPE_STORE,
            'default'
        );
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        unset($_COOKIE['_ga'], $_COOKIE[self::VARY_COOKIE]);
        parent::tearDown();
    }

    /**
     * Two browser events from the store's own page write two outbox rows; the same body again writes none
     *
     * @magentoConfigFixture base_website hryvinskyi_datalayer_server/general/enabled 1
     * @magentoConfigFixture current_store hryvinskyi_datalayer/general/enabled 1
     * @return void
     */
    public function testTwoBrowserEventsAreStoredOnce(): void
    {
        $body = $this->body([
            $this->viewItemList('0c5b1a6e-8f43-4c9a-9f0e-2d7a7f1b9c01'),
            $this->selectItem('9f86d081884c7d659a2feaa0c55ad015'),
        ]);

        $this->post($body, $this->storeOrigin());
        $this->assertNoContent();
        $rows = $this->rows(self::OUTBOX, 'client_id = ?', self::CLIENT_ID);
        self::assertCount(2, $rows);
        self::assertSame(
            [
                sha1('collect:' . self::CLIENT_ID . ':0c5b1a6e-8f43-4c9a-9f0e-2d7a7f1b9c01'),
                sha1('collect:' . self::CLIENT_ID . ':9f86d081884c7d659a2feaa0c55ad015'),
            ],
            array_map(static fn (array $row): ?string => $row['dedup_key'] ?? null, $rows)
        );
        self::assertSame(['2', '2'], array_column($rows, 'priority'), 'Posted events are delivered last.');
        $payload = json_decode($rows[0]['payload'] ?? '', true);
        self::assertIsArray($payload);
        $sentEvents = $payload['events'] ?? null;
        self::assertIsArray($sentEvents);
        $sent = $sentEvents[0] ?? null;
        self::assertIsArray($sent);
        self::assertSame('view_item_list', $sent['name'] ?? null);
        $params = $sent['params'] ?? null;
        self::assertIsArray($params);
        self::assertSame('https://store.example/category.html', $params['page_location'] ?? null);

        $this->post($body, $this->storeOrigin());
        $this->assertNoContent();
        self::assertCount(2, $this->rows(self::OUTBOX, 'client_id = ?', self::CLIENT_ID));
    }

    /**
     * A purchase posted by a browser is never taken, whatever the store routes
     *
     * @magentoConfigFixture base_website hryvinskyi_datalayer_server/general/enabled 1
     * @magentoConfigFixture current_store hryvinskyi_datalayer/general/enabled 1
     * @return void
     */
    public function testPurchaseIsNeverTaken(): void
    {
        $this->post($this->body([[
            'event' => 'purchase',
            'event_id' => 'b6f2c1d0-1111-4a2b-8c3d-000000000001',
            'ecommerce' => ['transaction_id' => '100000001', 'currency' => 'EUR', 'value' => 99.5],
        ]]), $this->storeOrigin());

        $this->assertNoContent();
        self::assertSame([], $this->rows(self::OUTBOX, 'client_id = ?', self::CLIENT_ID));
    }

    /**
     * With every known event enabled and routed to the server, a browser posting each of them gets exactly the events
     * registered as detected by the browser stored; server-detected and order events (cart lines, checkout steps,
     * account actions, purchase, refund and any other module's server-detected event) are never taken
     *
     * @magentoConfigFixture base_website hryvinskyi_datalayer_server/general/enabled 1
     * @magentoConfigFixture current_store hryvinskyi_datalayer/general/enabled 1
     * @return void
     */
    public function testOnlyBrowserDetectedEventsAreTaken(): void
    {
        $knownEvents = $this->service(KnownEventsInterface::class);
        $names = array_keys($knownEvents->getLabels());
        $routing = [];
        foreach ($names as $index => $name) {
            $routing['r' . $index] = ['event' => $name, 'channel' => 'server'];
        }
        $config = $this->service(MutableScopeConfigInterface::class);
        $config->setValue(
            'hryvinskyi_datalayer/channels/routing',
            (string) json_encode($routing),
            ScopeInterface::SCOPE_STORE,
            'default'
        );
        $config->setValue('hryvinskyi_datalayer/events/disabled', '', ScopeInterface::SCOPE_STORE, 'default');
        $availability = $this->service(EventAvailabilityInterface::class);
        $channels = $this->service(EventChannelPolicyInterface::class);
        $forbidden = [
            'purchase', 'refund', 'add_to_cart', 'remove_from_cart', 'add_shipping_info', 'add_payment_info',
            'add_to_wishlist', 'login', 'sign_up',
        ];
        foreach ($forbidden as $name) {
            self::assertContains($name, $names, $name . ' is not a known event here.');
            self::assertTrue($availability->isEnabled($name, $this->storeId()), $name . ' is disabled.');
            self::assertTrue(
                $channels->channelOf($name, $this->storeId())->includesServer(),
                $name . ' does not go to the server, so its absence would prove nothing.'
            );
        }
        $browserDetected = array_values(array_filter(
            $names,
            static fn (string $name): bool => $knownEvents->isBrowserOriginated($name)
        ));
        foreach ([
            'view_item_list', 'select_item', 'view_item', 'view_cart', 'begin_checkout', 'view_promotion',
            'select_promotion',
        ] as $name) {
            self::assertContains($name, $browserDetected, $name . ' is not detected by the browser.');
        }
        self::assertSame([], array_values(array_intersect($browserDetected, $forbidden)));

        foreach (array_chunk($names, CollectServiceInterface::MAX_EVENTS) as $chunk) {
            $this->post(
                $this->body(array_map(fn (string $name): array => $this->anyEvent($name), $chunk)),
                $this->storeOrigin()
            );
            $this->assertNoContent();
        }

        $stored = [];
        foreach ($this->rows(self::OUTBOX, 'client_id = ?', self::CLIENT_ID) as $row) {
            $payload = json_decode($row['payload'] ?? '', true);
            self::assertIsArray($payload);
            foreach (is_array($payload['events'] ?? null) ? $payload['events'] : [] as $event) {
                self::assertIsArray($event);
                self::assertIsString($event['name'] ?? null);
                $stored[] = $event['name'];
            }
        }
        self::assertEqualsCanonicalizing($browserDetected, $stored);
        self::assertSame([], array_values(array_intersect($stored, $forbidden)));
    }

    /**
     * Events posted from a page of another site are not taken
     *
     * @magentoConfigFixture base_website hryvinskyi_datalayer_server/general/enabled 1
     * @magentoConfigFixture current_store hryvinskyi_datalayer/general/enabled 1
     * @return void
     */
    public function testForeignOriginIsRefused(): void
    {
        $this->post(
            $this->body([$this->viewItemList('0c5b1a6e-8f43-4c9a-9f0e-2d7a7f1b9c02')]),
            'https://elsewhere.example'
        );

        $this->assertNoContent();
        self::assertSame([], $this->rows(self::OUTBOX, 'client_id = ?', self::CLIENT_ID));
    }

    /**
     * A collector request starts no session at all and writes no visitor row
     *
     * Every session object starts the PHP session when it is built, so a recording start check sees any session the
     * request starts, from the application's launch to the response being sent; the sessions already built in this
     * process are dropped first, so that using one counts.
     *
     * @magentoConfigFixture base_website hryvinskyi_datalayer_server/general/enabled 1
     * @magentoConfigFixture current_store hryvinskyi_datalayer/general/enabled 1
     * @return void
     */
    public function testStartsNoSessionAndWritesNoVisitorRow(): void
    {
        $visitorRows = $this->visitorRowCount();
        $objectManager = $this->testObjectManager();
        $this->prepareRequest(
            $this->body([$this->viewItemList('0c5b1a6e-8f43-4c9a-9f0e-2d7a7f1b9c03')]),
            $this->storeOrigin(),
            '/hryvinskyi_datalayer/collect/'
        );
        $this->dropSharedSessions($objectManager);
        $startChecks = new RecordingSessionStartChecker();
        $objectManager->addSharedInstance($startChecks, SessionStartChecker::class, true);

        $this->_getBootstrap()->runApp();

        $this->assertNoContent();
        self::assertCount(1, $this->rows(self::OUTBOX, 'client_id = ?', self::CLIENT_ID));
        self::assertSame([], $startChecks->callers, 'A collector request started a session.');
        self::assertSame($visitorRows, $this->visitorRowCount());
        self::assertFalse($this->httpRequest()->isDispatched(), 'A collector request was routed.');
    }

    /**
     * Sending the customer id as the user id starts no session either: a request without the session cookie never
     * has its customer read
     *
     * @magentoConfigFixture base_website hryvinskyi_datalayer_server/general/enabled 1
     * @magentoConfigFixture base_website hryvinskyi_datalayer_server/identity/send_user_id 1
     * @magentoConfigFixture current_store hryvinskyi_datalayer/general/enabled 1
     * @return void
     */
    public function testSendingUserIdsStartsNoSession(): void
    {
        $objectManager = $this->testObjectManager();
        $this->prepareRequest(
            $this->body([$this->viewItemList('0c5b1a6e-8f43-4c9a-9f0e-2d7a7f1b9c0a')]),
            $this->storeOrigin(),
            '/hryvinskyi_datalayer/collect/'
        );
        $this->dropSharedSessions($objectManager);
        $startChecks = new RecordingSessionStartChecker();
        $objectManager->addSharedInstance($startChecks, SessionStartChecker::class, true);

        $this->_getBootstrap()->runApp();

        $this->assertNoContent();
        $rows = $this->rows(self::OUTBOX, 'client_id = ?', self::CLIENT_ID);
        self::assertCount(1, $rows);
        self::assertSame([], $startChecks->callers, 'A collector request started a session.');
        $payload = json_decode($rows[0]['payload'] ?? '', true);
        self::assertIsArray($payload);
        self::assertArrayNotHasKey('user_id', $payload);
    }

    /**
     * The page-cache variation cookie of a signed-in visitor survives a collector request
     *
     * @magentoConfigFixture base_website hryvinskyi_datalayer_server/general/enabled 1
     * @magentoConfigFixture current_store hryvinskyi_datalayer/general/enabled 1
     * @return void
     */
    public function testKeepsThePageCacheVariationCookie(): void
    {
        $_COOKIE[self::VARY_COOKIE] = 'vary-of-a-signed-in-visitor';

        $this->post($this->body([$this->viewItemList('0c5b1a6e-8f43-4c9a-9f0e-2d7a7f1b9c06')]), $this->storeOrigin());

        $this->assertNoContent();
        self::assertSame('vary-of-a-signed-in-visitor', $_COOKIE[self::VARY_COOKIE] ?? null);
    }

    /**
     * A store code in front of the collector's path still reaches the collector
     *
     * @magentoConfigFixture web/url/use_store 1
     * @magentoConfigFixture base_website hryvinskyi_datalayer_server/general/enabled 1
     * @magentoConfigFixture current_store hryvinskyi_datalayer/general/enabled 1
     * @return void
     */
    public function testStoreCodeInThePathReachesTheCollector(): void
    {
        $this->post(
            $this->body([$this->viewItemList('0c5b1a6e-8f43-4c9a-9f0e-2d7a7f1b9c07')]),
            $this->storeOrigin(),
            '/default/hryvinskyi_datalayer/collect'
        );

        $this->assertNoContent();
        self::assertCount(1, $this->rows(self::OUTBOX, 'client_id = ?', self::CLIENT_ID));
    }

    /**
     * Any other POST goes through the front controller as before: it is routed, and here refused for its missing
     * form key
     *
     * @magentoConfigFixture base_website hryvinskyi_datalayer_server/general/enabled 1
     * @magentoConfigFixture current_store hryvinskyi_datalayer/general/enabled 1
     * @return void
     */
    public function testOtherRequestsGoThroughTheFrontController(): void
    {
        $this->post(
            $this->body([$this->viewItemList('0c5b1a6e-8f43-4c9a-9f0e-2d7a7f1b9c08')]),
            $this->storeOrigin(),
            '/customer/account/loginPost/'
        );

        self::assertTrue($this->httpRequest()->isDispatched(), 'A POST to another path was not routed.');
        $response = $this->testObjectManager()->get(ResponseInterface::class);
        self::assertInstanceOf(Response::class, $response);
        self::assertNotSame(204, $response->getHttpResponseCode());
        self::assertSame([], $this->rows(self::OUTBOX, 'client_id = ?', self::CLIENT_ID));
    }

    /**
     * A store's address of the collector is its own, and routes to the collector
     *
     * @magentoConfigFixture base_website hryvinskyi_datalayer_server/general/enabled 1
     * @magentoConfigFixture current_store hryvinskyi_datalayer/general/enabled 1
     * @return void
     */
    public function testCollectUrlIsTheStoresOwnAndRoutesToTheCollector(): void
    {
        $url = $this->service(CollectUrlInterface::class)->url($this->storeId());

        $origin = $this->storeOrigin();
        self::assertIsString($url);
        self::assertStringStartsWith($origin . '/', $url);
        self::assertStringNotContainsString('SID', $url);

        $this->post(
            $this->body([$this->viewItemList('0c5b1a6e-8f43-4c9a-9f0e-2d7a7f1b9c04')]),
            $origin,
            substr($url, strlen($origin))
        );
        $this->assertNoContent();
        self::assertCount(1, $this->rows(self::OUTBOX, 'client_id = ?', self::CLIENT_ID));
    }

    /**
     * Without server-side delivery a store has no collector address
     *
     * @return void
     */
    public function testNoCollectUrlWhenDisabled(): void
    {
        self::assertNull($this->service(CollectUrlInterface::class)->url($this->storeId()));
    }

    /**
     * Post a body to the collector the way a browser's beacon does
     *
     * @param string $body
     * @param string $origin
     * @param string $path
     * @return void
     */
    private function post(string $body, string $origin, string $path = '/hryvinskyi_datalayer/collect/'): void
    {
        $this->prepareRequest($body, $origin, $path);
        $this->_getBootstrap()->runApp();
    }

    /**
     * Make the shared request a browser's beacon, with a fresh response to answer it
     *
     * @param string $body
     * @param string $origin
     * @param string $path
     * @return void
     */
    private function prepareRequest(string $body, string $origin, string $path): void
    {
        $this->testObjectManager()->removeSharedInstance(ResponseInterface::class);
        $request = $this->getRequest();
        self::assertInstanceOf(Request::class, $request);
        $request->setMethod('POST');
        $request->setContent($body);
        $headers = $request->getHeaders();
        self::assertInstanceOf(Headers::class, $headers);
        $headers->clearHeaders();
        $headers->addHeaders([
            'Origin' => $origin,
            'Content-Type' => 'application/json',
            'User-Agent' => self::USER_AGENT,
        ]);
        $request->setDispatched(false);
        $request->setRequestUri($path);
    }

    /**
     * Get the shared request as the integration framework's request
     *
     * @return Request
     */
    private function httpRequest(): Request
    {
        $request = $this->getRequest();
        self::assertInstanceOf(Request::class, $request);

        return $request;
    }

    /**
     * Get the integration framework's object manager
     *
     * @return ObjectManager
     */
    private function testObjectManager(): ObjectManager
    {
        $objectManager = Bootstrap::getObjectManager();
        self::assertInstanceOf(ObjectManager::class, $objectManager);

        return $objectManager;
    }

    /**
     * Assert that the last answer was empty and not to be stored
     *
     * @return void
     */
    private function assertNoContent(): void
    {
        $response = Bootstrap::getObjectManager()->get(ResponseInterface::class);
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(204, $response->getHttpResponseCode());
        self::assertSame('', (string) $response->getBody());
        $cacheControl = $response->getHeader('Cache-Control');
        self::assertInstanceOf(HeaderInterface::class, $cacheControl);
        self::assertSame('no-store', $cacheControl->getFieldValue());
    }

    /**
     * Build a request body
     *
     * @param list<array<string, mixed>> $events
     * @return string
     */
    private function body(array $events): string
    {
        return (string) json_encode([
            'events' => $events,
            'page_location' => 'https://store.example/category.html',
            'page_referrer' => '',
        ]);
    }

    /**
     * Build a list impression as the browser posts it
     *
     * @param string $eventId
     * @return array<string, mixed>
     */
    private function viewItemList(string $eventId): array
    {
        return [
            'event' => 'view_item_list',
            'ecommerce' => [
                'item_list_id' => 'category_3',
                'item_list_name' => 'Laptops',
                'items' => [['item_id' => 'SKU-1', 'item_name' => 'Laptop', 'price' => 499.99, 'index' => 0]],
            ],
            'event_id' => $eventId,
        ];
    }

    /**
     * Build any event as a browser could post it: an ecommerce object with money and one item, under an event id
     * of its own
     *
     * @param string $name
     * @return array<string, mixed>
     */
    private function anyEvent(string $name): array
    {
        return [
            'event' => $name,
            'ecommerce' => [
                'currency' => 'EUR',
                'value' => 499.99,
                'items' => [['item_id' => 'SKU-1', 'item_name' => 'Laptop', 'price' => 499.99, 'quantity' => 1]],
            ],
            'event_id' => 'known-' . substr(sha1($name), 0, 30),
        ];
    }

    /**
     * Build an item selection as the browser posts it
     *
     * @param string $eventId
     * @return array<string, mixed>
     */
    private function selectItem(string $eventId): array
    {
        return [
            'event' => 'select_item',
            'ecommerce' => [
                'item_list_name' => 'Laptops',
                'items' => [['item_id' => 'SKU-1', 'item_name' => 'Laptop', 'index' => 0]],
            ],
            'event_id' => $eventId,
        ];
    }

    /**
     * Get the origin of the store's own pages: the scheme and host of its base URL
     *
     * @return non-empty-string
     */
    private function storeOrigin(): string
    {
        $baseUrl = $this->service(UrlInterface::class)->getBaseUrl();
        self::assertSame(1, preg_match('~^https?://[^/]+~', $baseUrl, $match));

        return $match[0];
    }

    /**
     * Get the default store view's id
     *
     * @return int
     */
    private function storeId(): int
    {
        return (int) $this->service(StoreManagerInterface::class)->getStore('default')->getId();
    }

    /**
     * Count the stored visitor rows
     *
     * @return int
     */
    private function visitorRowCount(): int
    {
        $resource = $this->service(ResourceConnection::class);
        $connection = $resource->getConnection();
        $count = $connection->fetchOne(
            $connection->select()->from($resource->getTableName('customer_visitor'), ['COUNT(*)'])
        );

        return is_numeric($count) ? (int) $count : -1;
    }

    /**
     * Drop every session object the container already shares, so that the next use of one builds it anew
     *
     * @param ObjectManager $objectManager
     * @return void
     */
    private function dropSharedSessions(ObjectManager $objectManager): void
    {
        $shared = (new \ReflectionProperty(\Magento\Framework\ObjectManager\ObjectManager::class, '_sharedInstances'))
            ->getValue($objectManager);
        foreach (is_array($shared) ? $shared : [] as $type => $instance) {
            if ($instance instanceof SessionManagerInterface) {
                $objectManager->removeSharedInstance((string) $type);
            }
        }
    }
}
