<?php

namespace Snicco\Component\HttpRouting\Tests\Http;

use PHPUnit\Framework\TestCase;
use Snicco\Component\HttpRouting\Http\Psr7\Request;
use Snicco\Component\HttpRouting\Http\ResponsePreparation;
use Snicco\Component\HttpRouting\Http\Psr7\DefaultResponseFactory;
use Snicco\Component\HttpRouting\Tests\helpers\CreateUrlGenerator;
use Snicco\Component\HttpRouting\Tests\helpers\CreateTestPsr17Factories;

class ResponsePreparationTest extends TestCase
{
    
    use CreateTestPsr17Factories;
    use CreateUrlGenerator;
    
    private DefaultResponseFactory $factory;
    private ResponsePreparation    $preparation;
    private Request                $request;
    
    protected function setUp() :void
    {
        parent::setUp();
        $this->factory = $this->createResponseFactory($this->createUrlGenerator());
        $this->preparation = new ResponsePreparation($this->psrStreamFactory());
        $this->request = new Request(
            $this->psrServerRequestFactory()->createServerRequest('GET', ' /foo')
        );
    }
    
    /** @test */
    public function testDateIsSetIfNotSetAlready()
    {
        $response = $this->factory->make();
        
        $response = $this->preparation->prepare($response, $this->request, []);
        
        $this->assertSame(gmdate('D, d M Y H:i:s').' GMT', $response->getHeaderLine('date'));
    }
    
    /** @test */
    public function testDateIsNotModifiedIfAlreadySet()
    {
        $date = gmdate('D, d M Y H:i:s T', time() + 10);
        $response = $this->factory->make()
                                  ->withHeader('date', $date);
        
        $response = $this->preparation->prepare($response, $this->request, []);
        
        $this->assertSame($date, $response->getHeaderLine('date'));
    }
    
    /** @test */
    public function testCacheControlDefaultsAreAdded()
    {
        $response = $this->factory->make();
        $response = $this->preparation->prepare($response, $this->request, []);
        
        $this->assertStringContainsString('no-cache', $response->getHeaderLine('cache-control'));
        $this->assertStringContainsString('private', $response->getHeaderLine('cache-control'));
    }
    
    /**
     * @test
     */
    public function cache_control_is_not_added_if_already_present_or_sent_by_a_call_to_header()
    {
        $response = $this->factory->make()->withHeader('cache-control', 'public');
        $response = $this->preparation->prepare($response, $this->request, []);
        $this->assertSame('public', $response->getHeaderLine('cache-control'));
        
        $response = $this->factory->make();
        $response = $this->preparation->prepare(
            $response,
            $this->request,
            ['Cache-Control: no-cache, must-revalidate, max-age=0']
        );
        $this->assertSame(
            'no-cache, must-revalidate, max-age=0, private',
            $response->getHeaderLine('cache-control')
        );
    }
    
    /** @test */
    public function testCacheControlHeadersWithValidatorsPresent()
    {
        $date = gmdate('D, d M Y H:i:s T', time() + 10);
        $response = $this->factory->make()->withHeader('Expires', $date);
        $response = $this->preparation->prepare($response, $this->request, []);
        $this->assertSame('private, must-revalidate', $response->getHeaderLine('cache-control'));
        
        $response = $this->factory->make()
                                  ->withHeader('Last-Modified', gmdate('D, d M Y H:i:s T', 10));
        
        $response = $this->preparation->prepare($response, $this->request, []);
        $this->assertSame('private, must-revalidate', $response->getHeaderLine('cache-control'));
    }
    
    /** @test */
    public function testFixesInformationalResponses()
    {
        $response = $this->factory->html('foo', 100)
                                  ->withHeader('content-length', 3);
        
        $prepared = $this->preparation->prepare($response, $this->request, []);
        $this->assertSame(0, $prepared->getBody()->getSize());
        $this->assertSame('', $prepared->getHeaderLine('content-type'));
        $this->assertSame('', $prepared->getHeaderLine('content-length'));
        $this->assertSame('', ini_get('default_mimetype'));
    }
    
    /** @test */
    public function testAddContentTypeIfNotPresent()
    {
        $response = $this->factory->make()->withBody($this->factory->createStream('foo'));
        $prepared = $this->preparation->prepare($response, $this->request, []);
        $this->assertSame('text/html; charset=UTF-8', $prepared->getHeaderLine('content-type'));
        
        // with charset if content type present
        $prepared = $this->preparation->prepare(
            $response->withContentType('text/html'),
            $this->request,
            []
        );
        $this->assertSame('text/html; charset=UTF-8', $prepared->getHeaderLine('content-type'));
        
        // with charset if content type present with ;
        $prepared = $this->preparation->prepare(
            $response->withContentType('text/html;'),
            $this->request,
            []
        );
        $this->assertSame('text/html; charset=UTF-8', $prepared->getHeaderLine('content-type'));
    }
    
    /** @test */
    public function testRemoveContentLengthIfTransferEncoding()
    {
        $response = $this->factory->make()
                                  ->withBody($this->factory->createStream('foo'))
                                  ->withHeader('content-length', 3)
                                  ->withHeader('transfer-encoding', 'chunked');
        
        $prepared = $this->preparation->prepare($response, $this->request, []);
        
        $this->assertFalse($prepared->hasHeader('content-length'));
    }
    
    /** @test */
    public function testHeadRequestRemovesBody()
    {
        $response = $this->factory->html('foo');
        
        $prepared = $this->preparation->prepare($response, $this->request->withMethod('HEAD'), []);
        
        $this->assertSame(0, $prepared->getBody()->getSize());
    }
    
    /** @test */
    public function testContentLength()
    {
        $response = $this->factory->html(str_repeat('a', 40));
        
        $prepared = $this->preparation->prepare($response, $this->request, []);
        
        $this->assertSame('40', $prepared->getHeaderLine('content-length'));
    }
    
    /** @test */
    public function no_content_length_if_output_buffering_is_on_and_has_content()
    {
        $response = $this->factory->html(str_repeat('a', 40));
        ob_start();
        echo 'foo';
        
        $prepared = $this->preparation->prepare($response, $this->request, []);
        
        $this->assertFalse($prepared->hasHeader('content-length'));
        ob_end_clean();
    }
    
    /** @test */
    public function no_content_length_if_empty_response_stream()
    {
        $response = $this->factory->html('');
        
        $prepared = $this->preparation->prepare($response, $this->request, []);
        
        $this->assertFalse($prepared->hasHeader('content-length'));
    }
    
}