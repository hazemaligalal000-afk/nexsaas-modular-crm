<?php

namespace ModularCore\Modules\Intelligence\Competitors;

/**
 * Competitor Intelligence Service: Regional Market Positioning (Requirement Piece 1)
 * Orchestrates competitor pricing, feature matrices, and sales battlecards for MENA.
 */
class CompetitorService
{
    /**
     * Requirement: Comparative Feature Matrix (Zoho, HubSpot, Odoo vs. NexSaaS)
     */
    public function getFeatureMatrix($region = 'EGP')
    {
        return [
            'NexSaaS' => [
                'vat_compliance' => true,
                'zatca_phase_2' => true,
                'whatsapp_native' => true,
                'local_payment_methods' => true, // Fawry, Paymob
                'arabic_first' => true
            ],
            'HubSpot' => [
                'vat_compliance' => false,
                'zatca_phase_2' => false,
                'whatsapp_native' => 'via Plugin',
                'local_payment_methods' => false,
                'arabic_first' => false
            ],
            'Odoo' => [
                'vat_compliance' => true,
                'zatca_phase_2' => 'via Partner',
                'whatsapp_native' => true,
                'local_payment_methods' => 'limited',
                'arabic_first' => true
            ]
        ];
    }

    /**
     * Requirement: Strategic Sales Scripts (Battlecards) per Competitor
     */
    public function getBattlecard($competitorId)
    {
        $battlecards = [
            'hubspot' => [
                'weakness' => 'Extremely expensive for MENA SMEs; No ZATCA/ETA compliance; USD-only billing.',
                'sales_angle' => 'Switch to NexSaaS to save 70% while getting native Saudi/Egypt tax sync.',
                'objection_handling' => 'If they ask about brand, highlight HubSpot’s lack of local support.'
            ],
            'zoho' => [
                'weakness' => 'Complex UI; Hard to customize for local workflows; Generic support.',
                'sales_angle' => 'NexSaaS is built specifically for the Arab business workflow, not just translated.'
            ]
        ];

        return $battlecards[$competitorId] ?? null;
    }

    /**
     * Requirement: Real-time Pricing Intelligence
     */
    public function getPricingBenchmarks()
    {
        return \DB::table('market_pricing')
            ->select(['competitor_name', 'plan_name', 'price_usd', 'price_egp', 'last_checked_at'])
            ->orderBy('price_usd', 'ASC')
            ->get();
    }
}
