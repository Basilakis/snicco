<?php

declare(strict_types=1);

namespace Snicco\Component\HttpRouting\Tests\Http;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Snicco\Component\HttpRouting\Http\Cookie;
use Snicco\Component\HttpRouting\Http\Psr7\Response;
use Snicco\Component\HttpRouting\Http\Psr7\ResponseFactory;
use Snicco\Component\HttpRouting\Tests\helpers\CreateTestPsr17Factories;
use Snicco\Component\HttpRouting\Tests\helpers\CreateUrlGenerator;

use const JSON_THROW_ON_ERROR;

class ResponseTest extends TestCase
{

    use CreateTestPsr17Factories;
    use CreateUrlGenerator;

    private ResponseFactory $factory;

    private Response $response;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createResponseFactory($this->createUrlGenerator());
        $this->response = $this->factory->make();
    }

    public function testIsPsrResponse(): void
    {
        $response = $this->factory->createResponse();
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testIsImmutable(): void
    {
        $response1 = $this->factory->make();
        $response2 = $response1->withHeader('foo', 'bar');

        $this->assertNotSame($response1, $response2);
        $this->assertTrue($response2->hasHeader('foo'));
        $this->assertFalse($response1->hasHeader('foo'));
    }

    public function testNonPsrAttributesAreCopiedForNewInstances(): void
    {
        $response1 = $this->factory->createResponse();
        $response1->foo = 'bar';

        $response2 = $response1->withHeader('foo', 'bar');

        $this->assertNotSame($response1, $response2);
        $this->assertTrue($response2->hasHeader('foo'));
        $this->assertFalse($response1->hasHeader('foo'));

        $this->assertSame('bar', $response1->foo);
        $this->assertSame('bar', $response2->foo);
    }

    public function testHtml(): void
    {
        $stream = $this->factory->createStream('foo');

        $response = $this->factory->make()->html($stream);

        $this->assertSame('text/html; charset=UTF-8', $response->getHeaderLine('content-type'));
        $this->assertSame('foo', $response->getBody()->__toString());
    }

    public function testJson(): void
    {
        $stream = $this->factory->createStream(json_encode(['foo' => 'bar'], JSON_THROW_ON_ERROR));

        $response = $this->factory->make()->json($stream);

        $this->assertSame('application/json', $response->getHeaderLine('content-type'));
        $this->assertSame(['foo' => 'bar'], json_decode($response->getBody()->__toString(), true));
    }

    public function testNoIndex(): void
    {
        $response = $this->response->withNoIndex();
        $this->assertSame('noindex', $response->getHeaderLine('x-robots-tag'));

        $response = $this->response->withNoIndex('googlebot');
        $this->assertSame('googlebot: noindex', $response->getHeaderLine('x-robots-tag'));
    }

    public function testNoFollow(): void
    {
        $response = $this->response->withNoFollow();
        $this->assertSame('nofollow', $response->getHeaderLine('x-robots-tag'));

        $response = $this->response->withNoFollow('googlebot');
        $this->assertSame('googlebot: nofollow', $response->getHeaderLine('x-robots-tag'));
    }

    public function testNoRobots(): void
    {
        $response = $this->response->withNoRobots();
        $this->assertSame('none', $response->getHeaderLine('x-robots-tag'));

        $response = $this->response->withNoRobots('googlebot');
        $this->assertSame('googlebot: none', $response->getHeaderLine('x-robots-tag'));
    }

    public function testNoArchive(): void
    {
        $response = $this->response->withNoArchive();
        $this->assertSame('noarchive', $response->getHeaderLine('x-robots-tag'));

        $response = $this->response->withNoArchive('googlebot');
        $this->assertSame('googlebot: noarchive', $response->getHeaderLine('x-robots-tag'));
    }

    public function testIsInformational(): void
    {
        $response = $this->response->withStatus(100);
        $this->assertTrue($response->isInformational());
        $this->assertTrue($response->withStatus(199)->isInformational());
        $this->assertFalse($response->withStatus(200)->isInformational());
    }

    /**
     * @test
     */
    public function testIsRedirection(): void
    {
        $response = $this->response->withStatus(299);
        $this->assertFalse($response->isRedirection());
        $this->assertTrue($response->withStatus(300)->isRedirection());
        $this->assertFalse($response->withStatus(400)->isRedirection());
    }

    /**
     * @test
     */
    public function testIsClientError(): void
    {
        $response = $this->response->withStatus(399);
        $this->assertFalse($response->isClientError());
        $this->assertTrue($response->withStatus(400)->isClientError());
        $this->assertFalse($response->withStatus(500)->isClientError());
    }

    /**
     * @test
     */
    public function testIsServerError(): void
    {
        $response = $this->response->withStatus(499);
        $this->assertFalse($response->isServerError());
        $this->assertTrue($response->withStatus(500)->isServerError());
        $this->assertTrue($response->withStatus(599)->isServerError());
    }

    /**
     * @test
     */
    public function testHasEmptyBody(): void
    {
        $response = $this->factory->make();
        $this->assertTrue($response->hasEmptyBody());

        $response->getBody()->detach();
        $this->assertTrue($response->hasEmptyBody());

        $html_response = $this->factory->html('foobar');
        $this->assertFalse($html_response->hasEmptyBody());
    }

    /**
     * @test
     */
    public function testIsEmpty(): void
    {
        $response = $this->response->withStatus(204);
        $this->assertTrue($response->isEmpty());
        $this->assertTrue($response->withStatus(304)->isEmpty());
        $this->assertTrue($this->factory->html('foo')->withStatus(204)->isEmpty());
        $this->assertTrue($this->factory->html('foo')->withStatus(304)->isEmpty());
    }

    /**
     * @test
     */
    public function testWithCookie(): void
    {
        $response = $this->response->withCookie(new Cookie('foo', 'bar'));

        $cookies = $response->cookies()->toHeaders();
        $this->assertCount(1, $cookies);
        $this->assertCount(0, $this->response->cookies()->toHeaders());
    }

    /**
     * @test
     */
    public function testWithoutCookie(): void
    {
        $response = $this->response->withoutCookie('foo');

        $cookies = $response->cookies()->toHeaders();
        $this->assertCount(1, $cookies);
        $this->assertCount(0, $this->response->cookies()->toHeaders());
    }

    /**
     * @test
     */
    public function cookies_are_not_reset_in_nested_responses(): void
    {
        $redirect_response = $this->factory->make()->withCookie(new Cookie('foo', 'bar'));

        $response = new Response($redirect_response);

        $response = $response->withCookie(new Cookie('bar', 'baz'));

        $cookies = $response->cookies();

        $headers = $cookies->toHeaders();

        $this->assertCount(2, $headers);
        $this->assertStringStartsWith('foo=bar', $headers[0]);
        $this->assertStringStartsWith('bar=baz', $headers[1]);
    }

    /**
     * @test
     */
    public function test_withFlashMessages(): void
    {
        $response = $this->response->withFlashMessages(['foo' => 'bar']);

        $this->assertSame(['foo' => 'bar'], $response->flashMessages());
        $this->assertSame([], $this->response->flashMessages());

        $response = $this->response->withFlashMessages($arr = ['foo' => 'bar', 'bar' => 'baz']);

        $this->assertSame($arr, $response->flashMessages());
        $this->assertSame([], $this->response->flashMessages());

        $response_new = $response->withFlashMessages(['biz' => 'boom']);

        $this->assertSame(
            ['foo' => 'bar', 'bar' => 'baz', 'biz' => 'boom'],
            $response_new->flashMessages()
        );
        $this->assertSame($arr, $response->flashMessages());
    }

    /**
     * @test
     */
    public function testWithInput(): void
    {
        $response = $this->response->withOldInput(['foo' => 'bar']);

        $this->assertSame(['foo' => 'bar'], $response->oldInput());
        $this->assertSame([], $this->response->oldInput());

        $response = $this->response->withOldInput($arr = ['foo' => 'bar', 'bar' => 'baz']);

        $this->assertSame($arr, $response->oldInput());
        $this->assertSame([], $this->response->oldInput());

        $response_new = $response->withOldInput(['biz' => 'boom']);

        $this->assertSame(
            ['foo' => 'bar', 'bar' => 'baz', 'biz' => 'boom'],
            $response_new->oldInput()
        );
        $this->assertSame($arr, $response->oldInput());
    }

    /**
     * @test
     */
    public function testWithErrors(): void
    {
        $response = $this->response->withErrors(['foo' => 'bar']);

        $this->assertSame(['default' => ['foo' => ['bar']]], $response->errors());
        $this->assertSame([], $this->response->errors());

        $response = $this->response->withErrors(['foo' => ['bar', 'baz'], 'baz' => 'biz']);

        $this->assertSame(
            [
                'default' => [
                    'foo' => ['bar', 'baz'],
                    'baz' => ['biz'],
                ],
            ],
            $response->errors()
        );
        $this->assertSame([], $this->response->errors());

        $response = $this->response->withErrors(['foo' => 'bar']);

        $response_new = $response->withErrors(['bar' => 'baz']);
        $this->assertSame(['default' => ['foo' => ['bar']]], $response->errors());
        $this->assertSame(
            ['default' => ['foo' => ['bar'], 'bar' => ['baz']]],
            $response_new->errors()
        );

        $response = $this->response->withErrors(['foo' => 'bar'], 'namespace1');
        $this->assertSame(['namespace1' => ['foo' => ['bar']]], $response->errors());
        $this->assertSame([], $this->response->errors());
    }

}