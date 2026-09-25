<?php

declare(strict_types=1);

namespace Requirements\Source;

use RuntimeException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Downloads a source document over HTTP(S) within the 16 MiB limit.
 *
 * Requests time out after 20 seconds and follow at most five redirects; only 2xx responses
 * are accepted.
 */
final class Download
{
    /**
     * @param HttpClientInterface|null $client The client; a default client when null
     */
    public function __construct(private readonly ?HttpClientInterface $client = null)
    {
    }

    /**
     * Downloads a document.
     *
     * @param string $uri The HTTP(S) URL
     *
     * @return string The response body
     *
     * @throws RuntimeException When the status is not 2xx or the body exceeds 16 MiB
     * @throws ExceptionInterface When the transfer fails
     */
    public function get(string $uri): string
    {
        $client = $this->client ?? HttpClient::create();
        $response = $client->request('GET', $uri, ['timeout' => 20, 'max_duration' => 20, 'max_redirects' => 5, 'buffer' => false, 'headers' => ['User-Agent' => 'requirements/1']]);
        try {
            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                throw new RuntimeException("HTTP $status");
            }
            $content = '';
            foreach ($client->stream($response) as $chunk) {
                $content .= $chunk->getContent();
                if (strlen($content) > 16777216) {
                    throw new RuntimeException('16 MiB size limit exceeded.');
                }
            }
            return $content;
        } finally {
            $response->cancel();
        }
    }
}
