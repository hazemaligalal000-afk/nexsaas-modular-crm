<?php
/**
 * ModularCore/Modules/Platform/AI/Services/ArabicChatbotService.php
 * Regional AI Chatbot with Dialect-Tuning (Task 8 Implementation)
 * Fulfills the "Arabic WhatsApp NLP" and "Egyptian/GCC Tuning" requirements.
 */

namespace ModularCore\Modules\Platform\AI\Services;

class ArabicChatbotService {
    
    private $aiService;

    public function __construct(GlobalAIService $aiService) {
        $this->aiService = $aiService;
    }

    /**
     * Process an inbound message and generate a dialect-aware AI response
     */
    public function generateResponse(string $message, string $region = 'EG') {
        $prompts = [
            'EG' => "You are an Egyptian assistant. Reply in warm, professional Egyptian dialect (Ammiya). Tone: 'يا فندم' style.",
            'GCC' => "You are a Khaliji assistant. Reply in polite Saudi/GCC dialect. Tone: 'طال عمرك / يا طويل العمر' style."
        ];

        $systemPrompt = $prompts[$region] ?? $prompts['EG'];
        
        // This leverages the cached GlobalAIService
        $aiResponse = $this->aiService->getChatCompletion([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $message]
        ]);

        error_log("[AI CHAT] Dialect: {$region} | Response Generated for: " . substr($message, 0, 20) . "...");
        
        return $aiResponse;
    }
}
