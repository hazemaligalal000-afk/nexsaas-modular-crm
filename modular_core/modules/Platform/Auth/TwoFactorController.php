<?php
/**
 * Platform/Auth/TwoFactorController.php
 *
 * Handles TOTP 2FA endpoints:
 *   POST /api/v1/auth/2fa/enroll   — generate secret + QR + backup codes
 *   POST /api/v1/auth/2fa/verify   — confirm TOTP code and activate 2FA
 *
 * Both endpoints require an authenticated user (JWT); the front-controller
 * must set userId and tenantId on this controller before dispatching.
 *
 * All responses use BaseController::respond().
 *
 * Requirements: 4.6, 33.1, 33.2, 33.3, 33.4, 33.5
 */

declare(strict_types=1);

namespace Platform\Auth;

use Core\BaseController;
use Core\Response;

class TwoFactorController extends BaseController
{
    private TwoFactorService $twoFactorService;

    public function __construct(TwoFactorService $twoFactorService)
    {
        $this->twoFactorService = $twoFactorService;
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/auth/2fa/enroll
    // -------------------------------------------------------------------------

    /**
     * Enroll the authenticated user in TOTP 2FA.
     *
     * Returns the raw TOTP secret, a provisioning URI (for QR code rendering),
     * and 8 single-use backup codes.
     *
     * Requirements: 33.1, 33.4
     *
     * @return Response
     */
    public function enroll(): Response
    {
        if ($this->userId === '') {
            return $this->respond(null, 'Authentication required.', 401);
        }

        try {
            $result = $this->twoFactorService->enroll(
                (int) $this->userId,
                $this->tenantId
            );

            return $this->respond($result, null, 200);
        } catch (\RuntimeException $e) {
            $status = $e->getCode() >= 400 ? $e->getCode() : 500;
            return $this->respond(null, $e->getMessage(), $status);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/auth/2fa/verify
    // -------------------------------------------------------------------------

    /**
     * Verify a TOTP code to complete enrollment and activate 2FA.
     *
     * Expected JSON body:
     *   { "code": string }   — 6-digit TOTP code from authenticator app
     *
     * Requirements: 33.1, 33.3
     *
     * @param  array $body  Parsed request body
     * @return Response
     */
    public function verify(array $body): Response
    {
        if ($this->userId === '') {
            return $this->respond(null, 'Authentication required.', 401);
        }

        $code = trim((string) ($body['code'] ?? ''));

        if ($code === '') {
            return $this->respond(null, 'code is required.', 422);
        }

        try {
            $ok = $this->twoFactorService->verify(
                (int) $this->userId,
                $this->tenantId,
                $code
            );

            if (!$ok) {
                return $this->respond(null, 'Invalid or expired TOTP code.', 401);
            }

            return $this->respond(['message' => '2FA enabled successfully.'], null, 200);
        } catch (\RuntimeException $e) {
            $status = $e->getCode() >= 400 ? $e->getCode() : 500;
            return $this->respond(null, $e->getMessage(), $status);
        }
    }
}
