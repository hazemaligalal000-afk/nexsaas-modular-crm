<?php

namespace ModularCore\Modules\Platform\Support;

use GuzzleHttp\Client;
use Exception;

/**
 * Task 12.1: Linear API Service (Support Ticketing)
 */
class LinearTicketService
{
    private $client;
    private $apiKey;
    private $teamId;

    public function __construct()
    {
        $this->apiKey = env('LINEAR_API_KEY');
        $this->teamId = env('LINEAR_TEAM_ID');
        $this->client = new Client([
            'base_uri' => 'https://api.linear.app/graphql',
            'headers'  => [
                'Authorization' => $this->apiKey,
                'Content-Type'  => 'application/json'
            ]
        ]);
    }

    /**
     * Requirement 10.1: Create ticket via Linear GraphQL
     */
    public function createTicket(string $title, string $description, string $tenantId, string $priority = 'low'): array
    {
        $query = '
        mutation IssueCreate($input: IssueCreateInput!) {
            issueCreate(input: $input) {
                success
                issue { id identifier url title }
            }
        }';

        $variables = [
            'input' => [
                'title'       => "[{$tenantId}] {$title}",
                'description' => $description,
                'teamId'      => $this->teamId,
                'priority'    => $this->mapPriority($priority),
                'labelIds'    => [env('LINEAR_SUPPORT_LABEL_ID')]
            ]
        ];

        try {
            $response = $this->client->post('', ['json' => ['query' => $query, 'variables' => $variables]]);
            $body = json_decode($response->getBody()->getContents(), true);
            
            if (!($body['data']['issueCreate']['success'] ?? false)) {
                throw new Exception("Linear API Error: Issue creation failed.");
            }

            return $body['data']['issueCreate']['issue'];

        } catch (Exception $e) {
            \Log::error("Support Ticket Sync Failed: " . $e->getMessage());
            throw $e;
        }
    }

    private function mapPriority(string $p): int
    {
        return match($p) {
            'urgent' => 1,
            'high'   => 2,
            'medium' => 3,
            default  => 4
        };
    }
}
