<?php
/**
 * Platform/RBAC/PermissionMiddleware.php
 *
 * Applied to every route. Verifies the authenticated user holds the required
 * permission before the request reaches the controller.
 *
 * On failure: returns HTTP 403 with a descriptive error in the standard
 * API_Response envelope (Requirement 2.5).
 *
 * Usage (in the router / front-controller):
 *
 *   $middleware = new PermissionMiddleware($rbacService, $controller);
 *   $response   = $middleware->handle($request, 'crm.contacts.create');
 *   if ($response !== null) {
 *       // 403 — send $response and stop
 *   }
 *   // else continue to controller action
 *
 * Requirements: 2.5
 */

declare(strict_types=1);

namespace Platform\RBAC;

use Core\BaseController;
use Core\Response;

class PermissionMiddleware
{
    private RBACService    $rbac;
    private BaseController $controller;

    /**
     * @param RBACService    $rbac        Configured for the current tenant.
     * @param BaseController $controller  Used only to build the 403 envelope.
     */
    public function __construct(RBACService $rbac, BaseController $controller)
    {
        $this->rbac       = $rbac;
        $this->controller = $controller;
    }

    // -------------------------------------------------------------------------
    // Middleware entry point
    // -------------------------------------------------------------------------

    /**
     * Check that $userId holds $permission.
     *
     * @param  int    $userId     Authenticated user's primary key.
     * @param  string $permission Required permission string (module.action).
     * @return Response|null      null = allowed; Response(403) = denied.
     *
     * Requirements: 2.5
     */
    public function handle(int $userId, string $permission): ?Response
    {
        if ($this->rbac->check($userId, $permission)) {
            return null; // access granted — continue to controller
        }

        // Access denied — return HTTP 403 with descriptive error (Requirement 2.5)
        return $this->controller->respond(
            null,
            sprintf(
                'Access denied: user %d does not hold the required permission "%s".',
                $userId,
                $permission
            ),
            403
        );
    }
}
