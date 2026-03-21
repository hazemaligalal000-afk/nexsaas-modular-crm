<?php
/**
 * Accounting/Zatca/ZatcaService.php
 * 
 * Generates ZATCA-compliant QR code TLV (Tag-Length-Value) strings.
 * Requirement: 3.3 (SME Compliance - Saudi VAT)
 */

namespace Accounting\Zatca;

class ZatcaService 
{
    /**
     * Generate the Base64 TLV string for ZATCA Phase 1 QR Code.
     * 
     * @param string $sellerName
     * @param string $vatNumber
     * @param string $timestamp ISO 8601
     * @param string $totalWithVat
     * @param string $vatTotal
     * @return string Base64 encoded TLV
     */
    public static function generateQrCode(
        string $sellerName, 
        string $vatNumber, 
        string $timestamp, 
        string $totalWithVat, 
        string $vatTotal
    ): string {
        $tlv = "";
        
        // Tag 1: Seller Name
        $tlv .= self::toTlv(1, $sellerName);
        // Tag 2: VAT Registration Number
        $tlv .= self::toTlv(2, $vatNumber);
        // Tag 3: Timestamp
        $tlv .= self::toTlv(3, $timestamp);
        // Tag 4: Invoice Total (with VAT)
        $tlv .= self::toTlv(4, $totalWithVat);
        // Tag 5: VAT Total
        $tlv .= self::toTlv(5, $vatTotal);
        
        return base64_encode($tlv);
    }

    private static function toTlv(int $tag, string $value): string {
        return chr($tag) . chr(strlen($value)) . $value;
    }
}
