<?php

namespace ModularCore\Modules\Omnichannel\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use ModularCore\Modules\Omnichannel\WhatsApp\WhatsAppService;
use Exception;

/**
 * Omnichannel Controller: Centralized Inbox & AI Integration
 */
class OmnichannelController extends Controller
{
    private $whatsapp;
    private $aiEndpoint = 'http://localhost:8001'; // Mock AI Engine (FastAPI) port

    public function __construct(WhatsAppService $whatsapp)
    {
        $this->whatsapp = $whatsapp;
    }

    /**
     * Requirement: Send Outgoing Message & Sync with AI
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'channel' => 'required|in:whatsapp,email,telegram,livechat',
            'to' => 'required',
            'text' => 'required',
            'contact_id' => 'required',
        ]);

        $status = ['success' => false];

        if ($request->channel === 'whatsapp') {
            $status = $this->whatsapp->sendMessage($request->to, $request->text);
        }

        // Logic to persist in omnichannel_messages table
        \DB::table('omnichannel_messages')->insert([
            'tenant_id' => $request->user()->tenant_id,
            'contact_id' => $request->contact_id,
            'channel' => $request->channel,
            'direction' => 'outbound',
            'message' => $request->text,
            'sent_at' => now(),
        ]);

        return response()->json($status);
    }

    /**
     * Requirement: AI Suggestion Bridge
     */
    public function getAISuggestions(Request $request)
    {
        $request->validate(['message' => 'required', 'context' => 'array']);

        $client = new Client();
        try {
            $response = $client->post("{$this->aiEndpoint}/api/v1/content/suggest-actions", [
                'json' => [
                    'message' => $request->message,
                    'context' => $request->context,
                ],
            ]);

            return response()->json(json_decode($response->getBody()->getContents(), true));
        } catch (Exception $e) {
            return response()->json(['error' => "AI Engine unreachable"], 500);
        }
    }

    /**
     * Requirement: AI Content Generator Bridge
     */
    public function getAIDraft(Request $request)
    {
        $request->validate(['message' => 'required', 'tone' => 'string']);

        $client = new Client();
        try {
            $response = $client->post("{$this->aiEndpoint}/api/v1/content/generate-reply", [
                'json' => [
                    'message' => $request->message,
                    'tone' => $request->tone,
                ],
            ]);

            return response()->json(json_decode($response->getBody()->getContents(), true));
        } catch (Exception $e) {
            return response()->json(['error' => "AI Content Gen failed"], 500);
        }
    }
}
