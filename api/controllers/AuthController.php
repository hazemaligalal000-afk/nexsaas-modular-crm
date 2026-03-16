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

        // Validate against vtiger_users table
        $sql = "SELECT id, user_name, organization_id, user_hash FROM vtiger_users WHERE user_name = ? AND deleted = 0 AND status = 'Active'";
        $result = $this->adb->pquery($sql, array($username));

        if ($this->adb->num_rows($result) > 0) {
            $row = $this->adb->fetch_array($result);
            
            // Password verification setup (In native Vtiger it's hashed, mimicking behavior here)
            // if (password_verify($password, $row['user_hash'])) {
            if ($password === 'password123') { // Placeholder for demo purposes
                $org_id = $row['organization_id'];
                
                // Generate a generic API key for session/frontend use
                $new_api_key = bin2hex(random_bytes(32));
                
                // Store the generated API Key
                $insert_key_sql = "INSERT INTO saas_api_keys (organization_id, api_key, description) VALUES (?, ?, 'Login Session Key')";
                $this->adb->pquery($insert_key_sql, array($org_id, $new_api_key));

                echo json_encode([
                    "status" => "success", 
                    "data" => [
                        "user_id" => $row['id'],
                        "organization_id" => $org_id,
                        "api_key" => $new_api_key
                    ],
                    "message" => "Authentication successful"
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
}
?>
