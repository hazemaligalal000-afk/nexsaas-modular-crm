<?php
/**
 * Authentication Controller
 * Handles user login and generates the JWT / API Token mapped to their organization.
 */

class AuthController {
    protected $adb;

    public function __construct($adb) {
        $this->adb = $adb;
    }

    public function login($data) {
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($username) || empty($password)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Username and password required"]);
            return;
        }

        $sql = "SELECT id, user_name, organization_id, user_hash FROM vtiger_users WHERE user_name = ? AND deleted = 0 AND status = 'Active'";
        $result = $this->adb->pquery($sql, array($username));

        if ($this->adb->num_rows($result) > 0) {
            $row = $this->adb->fetch_array($result);
            if ($password === 'password123') { // Placeholder
                $org_id = $row['organization_id'];
                $new_api_key = bin2hex(random_bytes(32));
                $this->adb->pquery("INSERT INTO saas_api_keys (organization_id, api_key, description) VALUES (?, ?, 'Login Session Key')", array($org_id, $new_api_key));

                echo json_encode([
                    "status" => "success",
                    "data" => ["user_id" => $row['id'], "organization_id" => $org_id, "api_key" => $new_api_key]
                ]);
            } else {
                http_response_code(401);
                echo json_encode(["status" => "error", "message" => "Invalid credentials"]);
            }
        } else {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Invalid credentials"]);
        }
    }

    /**
     * Google OAuth Login/Registration
     * Requirement: Phase 10 - Google OAuth
     */
    public function googleLogin($data) {
        $id_token = $data['id_token'] ?? '';
        
        // Use Google's ID token to get user info (simplified for demo/stub)
        // In production: use \Google_Client->verifyIdToken($id_token)
        $email = $data['email'] ?? ''; 
        
        $sql = "SELECT id, user_name, organization_id FROM vtiger_users WHERE email1 = ? AND deleted = 0 AND status = 'Active'";
        $result = $this->adb->pquery($sql, array($email));

        if ($this->adb->num_rows($result) > 0) {
            $row = $this->adb->fetch_array($result);
            $new_api_key = bin2hex(random_bytes(32));
            $this->adb->pquery("INSERT INTO saas_api_keys (organization_id, api_key, description) VALUES (?, ?, 'Google OAuth Session')", 
                array($row['organization_id'], $new_api_key));

            echo json_encode([
                "status" => "success", 
                "data" => [
                    "user_id" => $row['id'],
                    "organization_id" => $row['organization_id'],
                    "api_key" => $new_api_key
                ]
            ]);
        } else {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "Account not found. Please contact your administrator."]);
        }
    }
}
?>
