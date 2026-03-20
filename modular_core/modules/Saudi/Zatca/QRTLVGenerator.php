<?php

namespace ModularCore\Modules\Saudi\Zatca;

/**
 * QR TLV Generator: ZATCA Phase 1 & 2 Compliance (Saudi E-invoicing)
 * Encodes mandatory invoice data into the legally required Base64 TLV format.
 */
class QRTLVGenerator
{
    /**
     * Requirement: Tag 1 (Seller Name), Tag 2 (VAT Number), Tag 3 (Timestamp), 
     *             Tag 4 (Invoice Total), Tag 5 (VAT Total)
     */
    public function generate(string $seller, string $vatNumber, string $timestamp, float $total, float $vatTotal): string
    {
        $tlv = "";
        
        // Tag 1: Seller Name
        $tlv .= $this->toTlv(1, $seller);
        
        // Tag 2: Seller VAT Number
        $tlv .= $this->toTlv(2, $vatNumber);
        
        // Tag 3: Timestamp (ISO 8601)
        $tlv .= $this->toTlv(3, $timestamp);
        
        // Tag 4: Invoice Total (with VAT) as precision float
        $tlv .= $this->toTlv(4, number_format($total, 2, '.', ''));
        
        // Tag 5: VAT Total
        $tlv .= $this->toTlv(5, number_format($vatTotal, 2, '.', ''));
        
        return base64_encode($tlv);
    }

    /**
     * TLV Encoding Logic: [Tag (1 byte)][Length (1 byte)][Value (L bytes)]
     */
    private function toTlv($tag, $value): string
    {
        $length = strlen($value);
        return chr($tag) . chr($length) . $value;
    }
}
