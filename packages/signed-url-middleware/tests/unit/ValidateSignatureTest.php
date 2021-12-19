<?php

declare(strict_types=1);

namespace Tests\SignedUrlMiddleware\unit;

use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Snicco\SignedUrl\Secret;
use Psr\Log\Test\TestLogger;
use Snicco\SignedUrl\UrlSigner;
use Tests\Core\MiddlewareTestCase;
use Snicco\SignedUrl\Sha256Hasher;
use Snicco\Core\Http\Psr7\Request;
use Nyholm\Psr7\Factory\Psr17Factory;
use Snicco\SignedUrl\SignedUrlValidator;
use Snicco\SignedUrl\Storage\InMemoryStorage;
use Snicco\SignedUrlMiddleware\ValidateSignature;
use Snicco\SignedUrl\Exceptions\InvalidSignature;

final class ValidateSignatureTest extends MiddlewareTestCase
{
    
    /**
     * @var SignedUrlValidator
     */
    private $validator;
    
    /**
     * @var UrlSigner
     */
    private $signer;
    
    /**
     * @var InMemoryStorage
     */
    private $storage;
    
    protected function setUp() :void
    {
        parent::setUp();
        $this->signer = new UrlSigner(
            $storage = new InMemoryStorage(),
            $hasher = new Sha256Hasher(Secret::generate())
        );
        $this->storage = $storage;
        
        $this->validator = new SignedUrlValidator(
            $storage,
            $hasher
        );
    }
    
    /** @test */
    public function next_is_called_for_valid_signature()
    {
        $m = new ValidateSignature(
            $this->validator,
            new Psr17Factory(),
            $logger = new TestLogger()
        );
        
        $link = $this->signer->sign('/foo', 10);
        
        $request = $this->frontendRequest('GET', $link->asString());
        
        $response = $this->runMiddleware($m, $request);
        
        $response->assertNextMiddlewareCalled()->assertOk();
        
        $this->assertEmpty($logger->records);
    }
    
    /** @test */
    public function next_is_not_called_for_invalid_signature()
    {
        $m = new ValidateSignature(
            $this->validator,
            new Psr17Factory(),
            $logger = new TestLogger(),
        );
        
        $link = $this->signer->sign('/foo', 10);
        
        $request = $this->frontendRequest('GET', ltrim($link->asString(), '/').'/bar/');
        
        $response = $this->runMiddleware($m, $request);
        
        $response->assertNextMiddlewareNotCalled()->assertStatus(403);
        $response->assertSee('Your link is expired or invalid');
        
        $this->assertTrue($logger->hasWarningRecords());
    }
    
    /** @test */
    public function next_not_called_for_expired()
    {
        $m = new ValidateSignature(
            $this->validator,
            new Psr17Factory(),
            $logger = new TestLogger(),
        );
        
        $link = $this->signer->sign('/foo', 1);
        
        sleep(2);
        
        $request = $this->frontendRequest('GET', $link->asString());
        
        $response = $this->runMiddleware($m, $request);
        
        $response->assertNextMiddlewareNotCalled()->assertStatus(403);
        
        $this->assertTrue($logger->hasWarningRecords());
    }
    
    /** @test */
    public function next_not_called_for_used()
    {
        $m = new ValidateSignature(
            $this->validator,
            new Psr17Factory(),
            $logger = new TestLogger(),
        );
        
        $link = $this->signer->sign('/foo', 1, 2);
        
        $request = $this->frontendRequest('GET', $link->asString());
        
        $response = $this->runMiddleware($m, $request);
        $response->assertNextMiddlewareCalled()->assertStatus(200);
        $this->assertFalse($logger->hasWarningRecords());
        
        $response = $this->runMiddleware($m, $request);
        $response->assertNextMiddlewareCalled()->assertStatus(200);
        $this->assertFalse($logger->hasWarningRecords());
        
        $response = $this->runMiddleware($m, $request);
        $response->assertNextMiddlewareNotCalled()->assertStatus(403);
        $this->assertTrue($logger->hasWarningRecords());
    }
    
    /** @test */
    public function the_403_failure_response_can_be_customized()
    {
        $m = new ValidateSignature(
            $this->validator,
            new Psr17Factory(),
            new NullLogger(),
            function (Request $request) {
                return $request->path().'.FOOBAR';
            }
        );
        
        $link = $this->signer->sign('/foo', 10);
        
        $request = $this->frontendRequest('GET', ltrim($link->asString(), '/').'/bar/');
        
        $response = $this->runMiddleware($m, $request);
        
        $response->assertNextMiddlewareNotCalled()->assertStatus(403);
        $response->assertSee('/foo.FOOBAR');
    }
    
    /** @test */
    public function log_levels_can_be_customized()
    {
        $m = new ValidateSignature(
            $this->validator,
            new Psr17Factory(),
            $logger = new TestLogger(),
            function () {
            },
            [
                InvalidSignature::class => LogLevel::NOTICE,
            ]
        );
        
        $link = $this->signer->sign('/foo', 10);
        
        $request = $this->frontendRequest('GET', ltrim($link->asString(), '/').'/bar/');
        
        $response = $this->runMiddleware($m, $request);
        
        $response->assertNextMiddlewareNotCalled()->assertStatus(403);
        
        $this->assertFalse($logger->hasWarningRecords());
        $this->assertTrue($logger->hasNoticeRecords());
    }
    
    /** @test */
    public function test_exception_for_invalid_log_level()
    {
        $m = new ValidateSignature(
            $this->validator,
            new Psr17Factory(),
            $logger = new TestLogger(),
            function () {
            },
            [
                InvalidSignature::class => 'bogus',
            ]
        );
        
        $link = $this->signer->sign('/foo', 10);
        
        $request = $this->frontendRequest('GET', ltrim($link->asString(), '/').'/bar/');
        
        $this->expectExceptionMessage("Log level [bogus] is not a valid");
        
        $response = $this->runMiddleware($m, $request);
    }
    
    /** @test */
    public function additional_context_can_be_provided()
    {
        $m = new ValidateSignature(
            $this->validator,
            new Psr17Factory(),
            $logger = new TestLogger(),
            function () {
            },
            [],
            function (Request $request) {
                return $request->getHeaderLine('User-Agent');
            }
        );
        
        $current_request = $this->frontendRequest()->withHeader('User-Agent', 'foo');
        
        $link = $this->signer->sign('/foo', 10, 1, $current_request->getHeaderLine('User-Agent'));
        
        $new_request =
            $this->frontendRequest('GET', $link->asString())->withHeader('User-Agent', 'bar');
        
        // User agent header did not match
        $this->runMiddleware($m, $new_request)->assertForbidden();
        
        // User agent header matches
        $this->runMiddleware($m, $new_request->withHeader('User-Agent', 'foo'))->assertOk();
    }
    
}