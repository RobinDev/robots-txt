<?php

namespace Spatie\Robots;

use InvalidArgumentException;

class RobotsHeaders
{
    protected $robotHeadersProperties = [];

    public static function readFrom(string $source): self
    {
        $content = @file_get_contents($source);

        if ($content === false) {
            throw new InvalidArgumentException("Could not read from source `{$source}`");
        }

        return new self($http_response_header ?? []);
    }

    public static function create(array $headers): self
    {
        return new self($headers);
    }

    public function __construct(array $headers)
    {
        $this->robotHeadersProperties = $this->parseHeaders($headers);
    }

    public function mayIndex(string $userAgent = '*'): bool
    {
        return ! $this->noindex($userAgent);
    }

    public function mayFollow(string $userAgent = '*'): bool
    {
        return ! $this->nofollow($userAgent);
    }

    public function noindex(string $userAgent = '*'): bool
    {
        return
            // 1. We check for the suggested user-agent
            $this->robotHeadersProperties[$userAgent]['noindex']
            // 2. We check for the user-agent + wildcard (except if ua is *)
            ?? $this->robotHeadersProperties[$this->getWildCardUserAgent($userAgent)]['noindex']
            // 3. We check for all user-agent
            ?? $this->robotHeadersProperties['*']['noindex']
            // 4. noindex doesn't exist, we can go :)
            ?? false;

        return $this->robotHeadersProperties[$userAgent]['noindex'] ?? false;
    }

    public function nofollow(string $userAgent = '*'): bool
    {
        return
            $this->robotHeadersProperties[$userAgent]['nofollow']
            ?? $this->robotHeadersProperties[$this->getWildCardUserAgent($userAgent)]['nofollow']
            ?? $this->robotHeadersProperties['*']['nofollow']
            ?? false;
    }

    protected function getWildCardUserAgent(string $userAgent): ?string
    {
        if ($userAgent !== '*') {
            for ($i = 1; $i <= strlen($userAgent); $i++) {
                $wildCardUserAgent = substr($userAgent, 0, $i).'*';
                if (isset($this->robotHeadersProperties[$wildCardUserAgent])) {
                    return $wildCardUserAgent;
                }
            }
        }

        return null;
    }

    protected function parseHeaders(array $headers): array
    {
        $robotHeaders = $this->filterRobotHeaders($headers);

        return array_reduce($robotHeaders, function (array $parsedHeaders, $header) {
            $header = $this->normalizeHeaders($header);

            $headerParts = explode(':', $header);

            $userAgent = count($headerParts) === 3
                ? trim($headerParts[1])
                : '*';

            $options = end($headerParts);

            $parsedHeaders[$userAgent] = [
                'noindex' => strpos(strtolower($options), 'noindex') !== false,
                'nofollow' => strpos(strtolower($options), 'nofollow') !== false,
            ];

            return $parsedHeaders;
        }, []);
    }

    protected function filterRobotHeaders(array $headers): array
    {
        return array_filter($headers, function ($header) use ($headers) {
            $headerContent = $this->normalizeHeaders($headers[$header] ?? []);

            return strpos(strtolower($header), 'x-robots-tag') === 0
                || strpos(strtolower($headerContent), 'x-robots-tag') === 0;
        }, ARRAY_FILTER_USE_KEY);
    }

    protected function normalizeHeaders($headers): string
    {
        return implode(',', (array) $headers);
    }
}
