<?php

namespace Stytch\Core;

/**
 * Base class for API URL construction and path interpolation
 */
class ApiBase
{
    private string $baseUrl;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Build a URL for an API endpoint with path parameter substitution
     * 
     * @param string $path The API path template (e.g., "/v1/users/{user_id}")
     * @param array $data Data containing path parameters and other values
     * @return string The complete URL with parameters substituted
     */
    public function urlFor(string $path, array $data = []): string
    {
        // Replace path parameters like {user_id} with actual values
        $interpolatedPath = $this->interpolatePath($path, $data);
        
        return $this->baseUrl . $interpolatedPath;
    }

    /**
     * Interpolate path parameters in a URL template
     * 
     * @param string $path Path template with {param} placeholders
     * @param array $data Data containing parameter values
     * @return string Interpolated path
     */
    private function interpolatePath(string $path, array $data): string
    {
        return preg_replace_callback(
            '/\{([^}]+)\}/',
            function ($matches) use ($data) {
                $paramName = $matches[1];
                if (isset($data[$paramName])) {
                    return urlencode((string) $data[$paramName]);
                }
                // Keep the placeholder if no value is provided
                return $matches[0];
            },
            $path
        );
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}