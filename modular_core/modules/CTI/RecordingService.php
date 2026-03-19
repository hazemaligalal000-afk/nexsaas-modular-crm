<?php
/**
 * CTI/RecordingService.php
 *
 * Handles recording storage: S3 stream upload, presigned URL generation, GDPR delete.
 */

declare(strict_types=1);

namespace CTI;

use Core\BaseService;

class RecordingService extends BaseService
{
    private string $tenantId;
    private string $bucket;
    private string $region;
    private string $accessKey;
    private string $secretKey;

    public function __construct($db, string $tenantId, array $s3Config = [])
    {
        parent::__construct($db);
        $this->tenantId  = $tenantId;
        $this->bucket    = $s3Config['bucket']     ?? ($_ENV['S3_BUCKET']     ?? 'nexsaas-recordings');
        $this->region    = $s3Config['region']     ?? ($_ENV['S3_REGION']     ?? 'me-south-1');
        $this->accessKey = $s3Config['access_key'] ?? ($_ENV['AWS_ACCESS_KEY'] ?? '');
        $this->secretKey = $s3Config['secret_key'] ?? ($_ENV['AWS_SECRET_KEY'] ?? '');
    }

    /**
     * Stream a recording from a URL and upload to S3.
     * Returns the S3 key.
     */
    public function storeFromUrl(string $callSid, string $recordingUrl, string $format = 'mp3'): string
    {
        $s3Key = "recordings/{$this->tenantId}/{$callSid}.{$format}";

        // Stream download
        $tmpFile = tempnam(sys_get_temp_dir(), 'rec_');
        $fh      = fopen($tmpFile, 'wb');
        $ch      = curl_init($recordingUrl);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fh,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 120,
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fh);

        if ($httpCode !== 200) {
            @unlink($tmpFile);
            throw new \RuntimeException("Failed to download recording: HTTP {$httpCode}");
        }

        // Upload to S3 via presigned PUT
        $this->s3Put($s3Key, $tmpFile, "audio/{$format}");
        @unlink($tmpFile);

        return $s3Key;
    }

    /**
     * Generate a presigned URL for playback (valid 1 hour).
     */
    public function presignedUrl(string $s3Key, int $expiresIn = 3600): string
    {
        $expires   = time() + $expiresIn;
        $host      = "{$this->bucket}.s3.{$this->region}.amazonaws.com";
        $path      = '/' . ltrim($s3Key, '/');
        $stringToSign = "GET\n\n\n{$expires}\n/{$this->bucket}{$path}";
        $sig       = base64_encode(hash_hmac('sha1', $stringToSign, $this->secretKey, true));
        $query     = http_build_query([
            'AWSAccessKeyId' => $this->accessKey,
            'Expires'        => $expires,
            'Signature'      => $sig,
        ]);

        return "https://{$host}{$path}?{$query}";
    }

    /**
     * GDPR delete: remove from S3 and mark DB record as deleted.
     */
    public function gdprDelete(string $callSid): bool
    {
        $row = $this->db->Execute(
            'SELECT recording_s3_key FROM call_log WHERE call_sid = ? AND tenant_id = ?',
            [$callSid, $this->tenantId]
        );

        if ($row === false || $row->EOF || empty($row->fields['recording_s3_key'])) {
            return false;
        }

        $s3Key = $row->fields['recording_s3_key'];
        $this->s3Delete($s3Key);

        $this->db->Execute(
            "UPDATE call_log SET recording_url = NULL, recording_s3_key = NULL,
             recording_status = 'deleted', transcript_text = NULL,
             transcript_ar = NULL, transcript_en = NULL, updated_at = ?
             WHERE call_sid = ? AND tenant_id = ?",
            [$this->now(), $callSid, $this->tenantId]
        );

        return true;
    }

    // ── S3 helpers (AWS Signature V4 lite) ───────────────────────────────────

    private function s3Put(string $key, string $filePath, string $contentType): void
    {
        $host    = "s3.{$this->region}.amazonaws.com";
        $url     = "https://{$host}/{$this->bucket}/{$key}";
        $content = file_get_contents($filePath);
        $md5     = base64_encode(md5($content, true));

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $content,
            CURLOPT_HTTPHEADER     => [
                "Content-Type: {$contentType}",
                "Content-MD5: {$md5}",
                'x-amz-acl: private',
            ],
            CURLOPT_USERPWD        => "{$this->accessKey}:{$this->secretKey}",
            CURLOPT_TIMEOUT        => 120,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    private function s3Delete(string $key): void
    {
        $host = "s3.{$this->region}.amazonaws.com";
        $url  = "https://{$host}/{$this->bucket}/{$key}";
        $ch   = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_USERPWD        => "{$this->accessKey}:{$this->secretKey}",
            CURLOPT_TIMEOUT        => 30,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
