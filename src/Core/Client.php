<?php

namespace Stytch\Core;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;

/**
 * Core HTTP client for Stytch API
 */
class Client
{
    private GuzzleClient $httpClient;
    private string $projectId;
    private string $secret;
    private string $apiBase;
    private string $fraudApiBase;

    public function __construct(
        string $projectId,
        string $secret,
        string $environment = null,
        string $fraudEnvironment = null,
        string $customBaseUrl = null
    ) {
        $this->projectId = $projectId;
        $this->secret = $secret;

        // Determine API base URL
        if ($customBaseUrl) {
            $this->apiBase = $customBaseUrl;
        } elseif ($environment === 'live') {
            $this->apiBase = 'https://api.stytch.com';
        } elseif ($environment === 'test') {
            $this->apiBase = 'https://test.stytch.com';
        } else {
            // Auto-detect based on project ID
            $this->apiBase = str_starts_with($projectId, 'project-live-')
                ? 'https://api.stytch.com'
                : 'https://test.stytch.com';
        }

        // Determine fraud API base URL
        $this->fraudApiBase = $fraudEnvironment ?: 'https://telemetry.stytch.com';

        $this->httpClient = new GuzzleClient([
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Stytch PHP SDK v1.0.0',
                'Content-Type' => 'application/json',
            ],
            'auth' => [$projectId, $secret],
        ]);
    }

    /**
     * Make a GET request
     */
    public function get(string $path, array $params = [], $opts = []): array
    {
        return $this->request('GET', $path, ['query' => $params], $opts);
    }

    /**
     * Make a POST request
     */
    public function post(string $path, array $data = [], $opts = []): array
    {
        return $this->request('POST', $path, ['json' => $data], $opts);
    }

    /**
     * Make a PUT request
     */
    public function put(string $path, array $data = [], $opts = []): array
    {
        return $this->request('PUT', $path, ['json' => $data], $opts);
    }

    /**
     * Make a DELETE request
     */
    public function delete(string $path, array $data = [], $opts = []): array
    {
        return $this->request('DELETE', $path, ['json' => $data], $opts);
    }

    /**
     * Make an HTTP request
     */
    private function request(
        string $method,
        string $path,
        array $options = [],
        array $methodOptions = []
    ): array {
        try {
            // Extract parameters from both JSON and query data for path substitution
            $jsonData = $options['json'] ?? [];
            $queryData = $options['query'] ?? [];
            $allParams = array_merge($queryData, $jsonData); // JSON takes precedence

            $processedPath = $this->substitutePath($path, $allParams);

            // Remove path parameters from request data to avoid sending them in the body/query
            if (isset($options['json'])) {
                $cleanedJson = $this->removePathParams($path, $jsonData);
                if (empty($cleanedJson)) {
                    // Remove the json option entirely if it's empty to avoid sending [] instead of {}
                    unset($options['json']);
                } else {
                    $options['json'] = $cleanedJson;
                }
            }
            if (isset($options['query'])) {
                $options['query'] = $this->removePathParams($path, $queryData);
            }

            // Process additional headers from methodOptions parameter
            $additionalHeaders = [];
            if (!empty($methodOptions)) {
                foreach ($methodOptions as $optValue) {
                    $additionalHeaders = $optValue->addHeaders($additionalHeaders);
                }
            }

            // Add any additional headers to the request options
            if (!empty($additionalHeaders)) {
                $options['headers'] = array_merge($options['headers'] ?? [], $additionalHeaders);
            }

            $url = $this->apiBase . $processedPath;
            $response = $this->httpClient->request($method, $url, $options);

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON response: ' . json_last_error_msg());
            }

            return $data;
        } catch (RequestException $e) {
            $this->handleRequestException($e);
        }
        // Unreachable code, but required for phpstan
        return [];
    }

    /**
     * Substitute path parameters in the URL path
     */
    private function substitutePath(string $path, array $data): string
    {
        return preg_replace_callback('/\{([^}]+)\}/', function ($matches) use ($data) {
            $paramName = $matches[1];
            if (isset($data[$paramName])) {
                return $data[$paramName];
            }
            return $matches[0]; // Return unchanged if parameter not found
        }, $path);
    }

    /**
     * Remove path parameters from request data
     */
    private function removePathParams(string $path, array $data): array
    {
        $pathParams = [];
        preg_replace_callback('/\{([^}]+)\}/', function ($matches) use (&$pathParams) {
            $pathParams[] = $matches[1];
            return $matches[0];
        }, $path);

        $cleanedData = $data;
        foreach ($pathParams as $param) {
            unset($cleanedData[$param]);
        }

        return $cleanedData;
    }

    /**
     * Handle HTTP request exceptions
     */
    private function handleRequestException(RequestException $e): void
    {
        $response = $e->getResponse();

        if ($response) {
            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            $errorData = json_decode($body, true);
            $errorMessage = $errorData['error_message'] ?? 'HTTP ' . $statusCode . ' error';

            throw new StytchException($errorMessage, $statusCode, $errorData);
        }

        throw new StytchException('Network error: ' . $e->getMessage(), 0);
    }

    public function getProjectId(): string
    {
        return $this->projectId;
    }

    public function getApiBase(): string
    {
        return $this->apiBase;
    }
}
