<?php

namespace Stytch\Core;

/**
 * Base response class for all Stytch API responses
 */
abstract class Response
{
    public int $statusCode;
    public string $requestId;

    public function __construct(int $statusCode = 200, string $requestId = '')
    {
        $this->statusCode = $statusCode;
        $this->requestId = $requestId;
    }

    /**
     * Create a response instance from API response data
     * 
     * @param array $data Raw API response data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        $statusCode = $data['status_code'] ?? 200;
        $requestId = $data['request_id'] ?? '';
        
        $instance = new static($statusCode, $requestId);
        
        // Set properties from data, skipping status_code and request_id
        foreach ($data as $key => $value) {
            if ($key !== 'status_code' && $key !== 'request_id' && property_exists($instance, $key)) {
                $instance->$key = $value;
            }
        }
        
        return $instance;
    }

    /**
     * Convert response to array
     * 
     * @return array
     */
    public function toArray(): array
    {
        $result = [
            'status_code' => $this->statusCode,
            'request_id' => $this->requestId,
        ];

        // Add all other properties
        foreach (get_object_vars($this) as $key => $value) {
            if ($key !== 'statusCode' && $key !== 'requestId') {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}