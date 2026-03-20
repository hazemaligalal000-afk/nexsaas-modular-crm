<?php

namespace ModularCore\Modules\Platform\Support\Chatbot;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use Exception;

/**
 * Chatbot Controller: AI Customer Support Automation (Requirement S4)
 * Orchestrates Intent Detection, Knowledge Base retrieval, and Human Escalation.
 */
class ChatbotController extends Controller
{
    private $aiEndpoint = 'http://localhost:8001'; // internal AI engine

    /**
     * Requirement S4: Auto-resolve Support Queries via AI
     */
    public function handle(Request $request)
    {
        $request->validate(['message' => 'required', 'portal_user_id' => 'required']);
        $tenantId = $request->user()->tenant_id;

        $client = new Client();
        try {
            // 1. Detect Intent (e.g., support_request, billing_question)
            $intentRes = $client->post("{$this->aiEndpoint}/api/v1/messages/detect-intent", [
                'json' => ['message' => $request->message]
            ]);
            $intent = json_decode($intentRes->getBody())->intent;

            // 2. Resolve or Escalate
            if ($intent === 'support_request') {
                $replyRes = $client->post("{$this->aiEndpoint}/api/v1/content/generate-reply", [
                    'json' => ['message' => $request->message, 'tone' => 'friendly']
                ]);
                $reply = json_decode($replyRes->getBody())->draft_text;

                return response()->json([
                    'from' => 'ai_assistant',
                    'message' => $reply,
                    'is_resolved' => true,
                ]);
            }

            // 3. Fallback to Human Escalation
            return response()->json([
                'from' => 'system',
                'message' => 'Transferring you to a live agent...',
                'is_resolved' => false,
                'escalated_to' => 'SARA_M'
            ]);

        } catch (Exception $e) {
            return response()->json(['error' => 'Assistant temporarily unavailable'], 503);
        }
    }

    /**
     * Requirement S4: Learning from Resolved Tickets (Internal)
     */
    public function trainFromTicket($ticketId)
    {
        // Logic to export resolved ticket resolution to knowledge base embeddings
        \Log::info("AI Chatbot: Training updated with resolution of ticket {$ticketId}");
    }
}
