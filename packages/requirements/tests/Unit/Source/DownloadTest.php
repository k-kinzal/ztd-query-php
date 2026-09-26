<?php

declare(strict_types=1);

namespace Tests\Unit\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Requirements\Source\Download;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[CoversClass(Download::class)]
#[Small]
final class DownloadTest extends TestCase
{
    /**
     * @throws ExceptionInterface
     */
    public function testGetReturnsTheBody(): void
    {
        $download = new Download(new MockHttpClient(new MockResponse(['Names shall ', 'start with a letter.'])));

        self::assertSame('Names shall start with a letter.', $download->get('https://example.org/manual'));
    }

    /**
     * @throws ExceptionInterface
     */
    public function testGetSendsABoundedRequest(): void
    {
        $response = new MockResponse('Source text.');
        (new Download(new MockHttpClient($response)))->get('https://example.org/manual');
        $options = $response->getRequestOptions();

        self::assertSame('GET', $response->getRequestMethod());
        self::assertSame('https://example.org/manual', $response->getRequestUrl());
        self::assertEquals(20, $options['timeout']);
        self::assertEquals(20, $options['max_duration']);
        self::assertSame(5, $options['max_redirects']);
        self::assertFalse($options['buffer']);
        self::assertIsArray($options['headers']);
        self::assertContains('User-Agent: requirements/1', $options['headers']);
    }

    /**
     * @throws ExceptionInterface
     */
    #[DataProvider('providerGetSuccessfulStatus')]
    public function testGetAcceptsSuccessfulStatus(int $status): void
    {
        self::assertSame('Source text.', (new Download(new MockHttpClient(new MockResponse('Source text.', ['http_code' => $status]))))->get('https://example.org/manual'));
    }

    /**
     * @return array<string, array{int}>
     */
    public static function providerGetSuccessfulStatus(): array
    {
        return [
            'ok' => [200],
            'non-authoritative' => [203],
            'last success' => [299],
        ];
    }

    /**
     * @throws ExceptionInterface
     */
    #[DataProvider('providerGetErrorStatus')]
    public function testGetRejectsOtherStatus(int $status): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("HTTP $status");
        (new Download(new MockHttpClient(new MockResponse('Names shall start with a letter.', ['http_code' => $status]))))->get('https://example.org/manual');
    }

    /**
     * @return array<string, array{int}>
     */
    public static function providerGetErrorStatus(): array
    {
        return [
            'redirect' => [300],
            'not modified' => [304],
            'not found' => [404],
            'server error' => [500],
        ];
    }

    /**
     * @throws ExceptionInterface
     */
    public function testGetAcceptsTheSizeLimit(): void
    {
        self::assertSame(16777216, strlen((new Download(new MockHttpClient(new MockResponse(str_repeat('x', 16777216)))))->get('https://example.org/manual')));
    }

    /**
     * @throws ExceptionInterface
     */
    public function testGetRejectsBodiesOverTheSizeLimit(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('16 MiB size limit exceeded.');
        (new Download(new MockHttpClient(new MockResponse([str_repeat('x', 16777216), 'x']))))->get('https://example.org/manual');
    }

    /**
     * @throws ExceptionInterface
     */
    public function testGetPropagatesTransportFailures(): void
    {
        $this->expectException(TransportExceptionInterface::class);
        (new Download(new MockHttpClient(new MockResponse('', ['error' => 'Connection refused.']))))->get('https://example.org/manual');
    }

    /**
     * @throws ExceptionInterface
     */
    public function testGetUsesADefaultClient(): void
    {
        $this->expectException(TransportExceptionInterface::class);
        (new Download())->get('http://127.0.0.1:1/manual');
    }
}
