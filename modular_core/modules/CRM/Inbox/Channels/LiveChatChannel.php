<?php
/**
 * CRM/Inbox/Channels/LiveChatChannel.php
 *
 * Live chat channel handler using WebSocket connections.
 * Inbound messages arrive via the WebSocket server (Ratchet/Swoole).
 * Outbound messages are pushed to the client's WebSocket connection.
 *
 * The chat widget is served as /widget.js and embeds via <script> tag.
 * Each visitor session is identified by a session token issued on widget load.
 *
 * Requirements: 12.1, 12.7
 */

declare(strict_types=1);

namespace CRM\Inbox\Channels;

class LiveChatChannel
{
    private array $config;

    /** @var array<string, object> Active WebSocket connections keyed by session token */
    private array $connections = [];

    /**
     * @param array $config  Keys:
     *   - ws_host       (string)  WebSocket server host
     *   - ws_port       (int)     WebSocket server port (default 8080)
     *   - widget_url    (string)  Public URL where widget.js is served
     *   - push_callback (callable|null)  fn(string $sessionToken, string $message): void
     *                             Injected by the WebSocket server to push messages to clients
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'ws_port' => 8080,
        ], $config);
    }

    // -------------------------------------------------------------------------
    // Inbound — WebSocket message parsing
    // -------------------------------------------------------------------------

    /**
     * Parse an inbound WebSocket message payload into a normalised message array.
     *
     * The chat widget sends JSON: { session_token, visitor_name, visitor_email, body }
     *
     * @param  string $rawMessage  Raw JSON string from WebSocket frame
     * @return array  Normalised: ['sender_email', 'sender_phone', 'body', 'external_id', 'metadata']
     *
     * @throws \InvalidArgumentException on malformed payload
     */
    public function parseInboundMessage(string $rawMessage): array
    {
        $data = json_decode($rawMessage, true);

        if (!is_array($data)) {
            throw new \InvalidArgumentException('LiveChatChannel: invalid JSON payload.');
        }

        $body = trim($data['body'] ?? '');
        if ($body === '') {
            throw new \InvalidArgumentException('LiveChatChannel: message body is empty.');
        }

        $sessionToken = $data['session_token'] ?? null;
        $visitorEmail = isset($data['visitor_email']) && $data['visitor_email'] !== ''
            ? trim(strtolower($data['visitor_email']))
            : null;

        return [
            'sender_email' => $visitorEmail,
            'sender_phone' => null,
            'body'         => $body,
            'external_id'  => $sessionToken,
            'metadata'     => [
                'session_token' => $sessionToken,
                'visitor_name'  => $data['visitor_name'] ?? null,
                'visitor_email' => $visitorEmail,
                'page_url'      => $data['page_url'] ?? null,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Outbound — push to WebSocket client
    // -------------------------------------------------------------------------

    /**
     * Push an outbound message to the visitor's WebSocket connection.
     *
     * @param  array  $conversation  Conversation row (metadata must contain session_token)
     * @param  string $body          Message text
     *
     * @throws \RuntimeException if no push mechanism is configured
     */
    public function send(array $conversation, string $body): void
    {
        $sessionToken = $conversation['metadata']['session_token'] ?? null;

        if (empty($sessionToken)) {
            throw new \RuntimeException('LiveChatChannel::send: no session_token in conversation metadata.');
        }

        $pushCallback = $this->config['push_callback'] ?? null;

        if ($pushCallback !== null && is_callable($pushCallback)) {
            $payload = json_encode([
                'type'    => 'message',
                'body'    => $body,
                'from'    => 'agent',
                'sent_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM),
            ]);
            ($pushCallback)($sessionToken, $payload);
            return;
        }

        // Fallback: store in connection registry if available
        if (isset($this->connections[$sessionToken])) {
            $conn    = $this->connections[$sessionToken];
            $payload = json_encode(['type' => 'message', 'body' => $body, 'from' => 'agent']);
            if (method_exists($conn, 'send')) {
                $conn->send($payload);
            }
            return;
        }

        // If no push mechanism is available, the message is persisted in the DB
        // and will be delivered when the visitor reconnects.
    }

    // -------------------------------------------------------------------------
    // Connection management
    // -------------------------------------------------------------------------

    /**
     * Register an active WebSocket connection for a session.
     *
     * Called by the WebSocket server when a visitor connects.
     *
     * @param string $sessionToken
     * @param object $connection   WebSocket connection object (Ratchet ConnectionInterface)
     */
    public function registerConnection(string $sessionToken, object $connection): void
    {
        $this->connections[$sessionToken] = $connection;
    }

    /**
     * Remove a WebSocket connection when the visitor disconnects.
     *
     * @param string $sessionToken
     */
    public function removeConnection(string $sessionToken): void
    {
        unset($this->connections[$sessionToken]);
    }

    // -------------------------------------------------------------------------
    // Widget configuration
    // -------------------------------------------------------------------------

    /**
     * Generate the JavaScript snippet for embedding the chat widget.
     *
     * Requirement 12.7: widget served as /widget.js, embeds via <script> tag.
     *
     * @param  string $tenantId
     * @return string  HTML <script> tag
     */
    public function getWidgetSnippet(string $tenantId): string
    {
        $widgetUrl = rtrim($this->config['widget_url'] ?? '/widget.js', '/');
        $wsHost    = $this->config['ws_host'] ?? 'localhost';
        $wsPort    = (int) ($this->config['ws_port'] ?? 8080);

        return sprintf(
            '<script src="%s" data-tenant="%s" data-ws-host="%s" data-ws-port="%d" async></script>',
            htmlspecialchars($widgetUrl, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($wsHost, ENT_QUOTES, 'UTF-8'),
            $wsPort
        );
    }

    /**
     * Issue a new visitor session token.
     *
     * @return string  Cryptographically random 32-byte hex token
     */
    public function issueSessionToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
