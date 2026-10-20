<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayerServerSideFrontendUi\Test\Unit\Model\Collect\Rule;

use Hryvinskyi\DataLayerServerSide\Model\Collect\CollectRequest;
use Hryvinskyi\DataLayerServerSideFrontendUi\Model\Collect\Rule\BodySizeRule;
use Hryvinskyi\DataLayerServerSideFrontendUi\Model\Collect\Rule\EnabledWebsiteRule;
use Hryvinskyi\DataLayerServerSideFrontendUi\Model\Collect\Rule\IgnoredUserAgentRule;
use Hryvinskyi\DataLayerServerSideFrontendUi\Model\Collect\Rule\JsonContentTypeRule;
use Hryvinskyi\DataLayerServerSideFrontendUi\Model\Collect\Rule\SameOriginRule;
use Hryvinskyi\DataLayerFrontendUi\Model\Browser\StoreOrigin;
use Hryvinskyi\DataLayerServerSide\Test\Unit\Fake\BuildsCollaborators;
use Hryvinskyi\DataLayerServerSide\Test\Unit\Fake\FakeConfig;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Each condition a posted request must meet: enabled website, body size, JSON, same origin, no crawler
 */
class RequestRulesTest extends TestCase
{
    use BuildsCollaborators;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0 Safari/537.36';

    /**
     * Only a request whose origin, or else referrer, names the store's host is allowed
     *
     * @param string|null $origin
     * @param string|null $referer
     * @param bool $allowed
     * @return void
     */
    #[DataProvider('origins')]
    public function testSameOrigin(?string $origin, ?string $referer, bool $allowed): void
    {
        $url = $this->createMock(UrlInterface::class);
        $url->method('getBaseUrl')->willReturnCallback(
            static fn (array $params = []): string => ($params['_secure'] ?? false) === true
                ? 'https://secure.store.example/'
                : 'http://www.store.example/'
        );

        $rule = new SameOriginRule(new StoreOrigin($url));

        self::assertSame($allowed, $rule->allows(new CollectRequest($origin, $referer)));
    }

    /**
     * Origins and referrers, and whether they are the store's
     *
     * @return array<string, array{string|null, string|null, bool}>
     */
    public static function origins(): array
    {
        return [
            'the store\'s unsecure host' => ['http://www.store.example', null, true],
            'the store\'s secure host' => ['https://secure.store.example', null, true],
            'host in another case' => ['https://WWW.Store.Example', null, true],
            'another site' => ['https://elsewhere.example', null, false],
            'a host ending like the store\'s' => ['https://evil-www.store.example', null, false],
            'the store\'s name inside another host' => ['https://www.store.example.evil.example', null, false],
            'an opaque origin' => ['null', 'https://www.store.example/page.html', false],
            'another site with the store as referrer' => [
                'https://elsewhere.example',
                'https://www.store.example/page.html',
                false,
            ],
            'no origin, the store as referrer' => [null, 'https://www.store.example/laptops.html?p=2', true],
            'empty origin, the store as referrer' => ['', 'https://www.store.example/laptops.html', true],
            'no origin, another referrer' => [null, 'https://elsewhere.example/', false],
            'neither' => [null, null, false],
            'not a URL' => ['www.store.example', null, false],
            'the store\'s host with a port' => ['https://www.store.example:8443', null, true],
            'the store\'s name as credentials' => ['https://www.store.example@evil.example', null, false],
            'the store\'s name in the path of another host' => [null, 'https://evil.example/www.store.example', false],
        ];
    }

    /**
     * Only a body declared as JSON is allowed, whatever its parameters
     *
     * @param string|null $contentType
     * @param bool $allowed
     * @return void
     */
    #[DataProvider('contentTypes')]
    public function testJsonContentType(?string $contentType, bool $allowed): void
    {
        self::assertSame($allowed, (new JsonContentTypeRule())->allows(new CollectRequest(null, null, $contentType)));
    }

    /**
     * Content types, and whether they are JSON
     *
     * @return array<string, array{string|null, bool}>
     */
    public static function contentTypes(): array
    {
        return [
            'JSON' => ['application/json', true],
            'JSON with a charset' => ['application/json;charset=UTF-8', true],
            'JSON in another case' => [' Application/JSON ; charset=utf-8', true],
            'plain text' => ['text/plain;charset=UTF-8', false],
            'a form' => ['application/x-www-form-urlencoded', false],
            'a JSON look-alike' => ['application/jsonp', false],
            'none' => [null, false],
        ];
    }

    /**
     * Only a body of 1 to 16 384 bytes is allowed; a body not read for its declared size is refused
     *
     * @param string|null $body
     * @param bool $allowed
     * @return void
     */
    #[DataProvider('bodies')]
    public function testBodySize(?string $body, bool $allowed): void
    {
        self::assertSame($allowed, (new BodySizeRule())->allows(new CollectRequest(null, null, null, $body)));
    }

    /**
     * Bodies, and whether their size is allowed
     *
     * @return array<string, array{string|null, bool}>
     */
    public static function bodies(): array
    {
        return [
            'small' => ['{"events":[]}', true],
            'exactly 16 KB' => [str_repeat('a', 16384), true],
            'one byte over' => [str_repeat('a', 16385), false],
            'multibyte text counted in bytes' => [str_repeat('é', 8193), false],
            'empty' => ['', false],
            'not read' => [null, false],
        ];
    }

    /**
     * A crawler that runs scripts, matched in any case, and a request naming no user agent are refused
     *
     * @param string|null $userAgent
     * @param bool $allowed
     * @return void
     */
    #[DataProvider('userAgents')]
    public function testIgnoredUserAgents(?string $userAgent, bool $allowed): void
    {
        $rule = new IgnoredUserAgentRule(['googlebot' => 'Googlebot', 'headless' => 'HeadlessChrome']);

        self::assertSame($allowed, $rule->allows(new CollectRequest(null, null, null, null, $userAgent)));
    }

    /**
     * User agents, and whether they are allowed
     *
     * @return array<string, array{string|null, bool}>
     */
    public static function userAgents(): array
    {
        return [
            'a browser' => [self::BROWSER, true],
            'a crawler' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', false],
            'a crawler in another case' => ['mozilla/5.0 (compatible; GOOGLEBOT/2.1)', false],
            'a headless browser' => ['Mozilla/5.0 (X11; Linux x86_64) HeadlessChrome/130.0 Safari/537.36', false],
            'blank' => ['  ', false],
            'none' => [null, false],
        ];
    }

    /**
     * An ignored user agent that is not text is refused when the rule is built
     *
     * @return void
     */
    public function testIgnoredUserAgentMustBeText(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new IgnoredUserAgentRule(['broken' => ['Googlebot']]);
    }

    /**
     * A request is allowed only while the current store's website sends events from the server
     *
     * @return void
     */
    public function testEnabledWebsite(): void
    {
        $request = new CollectRequest();

        self::assertTrue((new EnabledWebsiteRule(new FakeConfig([1]), $this->storeManager()))->allows($request));
        self::assertFalse((new EnabledWebsiteRule(new FakeConfig([2]), $this->storeManager()))->allows($request));
    }
}
