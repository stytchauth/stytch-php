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
    public function get(string $path, array $params = []): array
    {
        return $this->request('GET', $path, ['query' => $params]);
    }

    /**
     * Make a POST request
     */
    public function post(string $path, array $data = []): array
    {
        return $this->request('POST', $path, ['json' => $data]);
    }

    /**
     * Make a PUT request
     */
    public function put(string $path, array $data = []): array
    {
        return $this->request('PUT', $path, ['json' => $data]);
    }

    /**
     * Make a DELETE request
     */
    public function delete(string $path): array
    {
        return $this->request('DELETE', $path);
    }

    /**
     * Make an HTTP request
     */
    private function request(string $method, string $path, array $options = []): array
    {
        try {
            $url = $this->apiBase . $path;
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