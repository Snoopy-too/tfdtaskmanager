<?php
declare(strict_types=1);

namespace App\Infrastructure\Http;

/**
 * Infrastructure HTTP adapter for publishing/unpublishing sandbox bundles to The Flying Dutchmen Game Lobby.
 */
class LobbyPublishClient
{
    private string $publishUrl;
    private string $publishSecret;
    private string $publicUrl;
    private int $timeoutSeconds;
    private bool $verifyTls;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $rawPublishUrl = (string) ($config['publish_url'] ?? getenv('TFD_LOBBY_PUBLISH_URL') ?: 'http://127.0.0.1:4002');
        $this->publishUrl = rtrim(trim($rawPublishUrl), '/');

        $rawSecret = (string) ($config['publish_secret'] ?? getenv('TFD_LOBBY_PUBLISH_SECRET') ?: '');
        $this->publishSecret = trim($rawSecret);

        $rawPublicUrl = (string) ($config['public_url'] ?? getenv('TFD_LOBBY_PUBLIC_URL') ?: 'https://lobby.theflyingdutchmen.games');
        $this->publicUrl = rtrim(trim($rawPublicUrl), '/');

        $this->timeoutSeconds = max(5, (int) ($config['timeout'] ?? 30));
        $this->verifyTls = (bool) ($config['verify_tls'] ?? true);
    }

    public function getPublicUrl(): string
    {
        return $this->publicUrl;
    }

    public function getPublishUrl(): string
    {
        return $this->publishUrl;
    }

    public function isConfigured(): bool
    {
        return $this->publishUrl !== '';
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function publishSandbox(array $payload): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode sandbox publish payload to JSON.');
        }

        return $this->sendRequest('POST', '/api/admin/published-sandboxes', $json);
    }

    /**
     * @return array<string, mixed>
     */
    public function unpublishSandbox(int $sandboxId): array
    {
        if ($sandboxId <= 0) {
            throw new \InvalidArgumentException('Invalid sandbox ID for unpublish.');
        }

        return $this->sendRequest('DELETE', '/api/admin/published-sandboxes/' . $sandboxId, '');
    }

    /**
     * @return array<string, mixed>
     */
    private function sendRequest(string $method, string $path, string $body): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Lobby publish URL is not configured in config.php.');
        }

        $url = $this->publishUrl . $path;
        $timestamp = (string) time();
        $headers = [
            'Accept: application/json',
            'X-TFD-Timestamp: ' . $timestamp,
        ];

        if ($body !== '') {
            $headers[] = 'Content-Type: application/json';
        }

        if ($this->publishSecret !== '') {
            $signature = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $this->publishSecret);
            $headers[] = 'X-TFD-Signature: ' . $signature;
        }

        if (function_exists('curl_init')) {
            return $this->sendViaCurl($method, $url, $headers, $body);
        }

        return $this->sendViaStream($method, $url, $headers, $body);
    }

    /**
     * @param array<int, string> $headers
     * @return array<string, mixed>
     */
    private function sendViaCurl(string $method, string $url, array $headers, string $body): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Unable to initialize HTTP client for lobby request.');
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeoutSeconds),
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
        ]);

        if ($body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($ch);
        $curlErr = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($responseBody === false) {
            throw new \RuntimeException('Lobby server unreachable: ' . $curlErr);
        }

        return $this->parseResponse($statusCode, (string) $responseBody);
    }

    /**
     * @param array<int, string> $headers
     * @return array<string, mixed>
     */
    private function sendViaStream(string $method, string $url, array $headers, string $body): array
    {
        $opts = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $body,
                'timeout' => (float) $this->timeoutSeconds,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => $this->verifyTls,
                'verify_peer_name' => $this->verifyTls,
            ],
        ];

        $context = stream_context_create($opts);
        $responseBody = @file_get_contents($url, false, $context);
        if ($responseBody === false) {
            throw new \RuntimeException('Lobby server unreachable at ' . $url);
        }

        $statusCode = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $hdr) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $hdr, $m)) {
                    $statusCode = (int) $m[1];
                }
            }
        }

        return $this->parseResponse($statusCode, $responseBody);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseResponse(int $statusCode, string $rawBody): array
    {
        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded)) {
            $decoded = ['raw' => $rawBody];
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $msg = (string) ($decoded['error'] ?? ('Lobby responded with HTTP ' . $statusCode));
            throw new \RuntimeException($msg);
        }

        return $decoded;
    }
}
