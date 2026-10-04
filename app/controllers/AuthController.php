<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class AuthController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        $this->call->database();
        $this->call->helper('json');
    }

    /** POST /api/register */
    public function register()
    {
        $in       = json_input();
        $username = (string) ($in['username'] ?? '');
        $email    = strtolower((string) ($in['email'] ?? ''));
        $password = (string) ($in['password'] ?? '');

        $errors = [];
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
            $errors['username'] = 'Username must be 3-50 characters (letters, numbers, _ . -).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $errors['email'] = 'A valid email is required.';
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }
        if ($errors) {
            $this->api->respond(['error' => 'Validation failed', 'errors' => $errors, 'status' => 422], 422);
        }

        $exists = $this->db->table('users')
            ->where('username', $username)
            ->or_where('email', $email)
            ->get();
        if ($exists) {
            $this->api->respond_error('Username or email is already taken.', 409);
        }

        $this->db->table('users')->insert([
            'username' => $username,
            'email'    => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role'     => 'user',
        ]);

        $this->api->respond([
            'message' => 'Account created. You can now log in.',
            'user'    => ['id' => (int) $this->db->last_id(), 'username' => $username, 'email' => $email, 'role' => 'user'],
        ], 201);
    }

    /** POST /api/login  (username OR email + password) */
    public function login()
    {
        $in         = json_input();
        $identifier = (string) ($in['username'] ?? $in['email'] ?? '');
        $password   = (string) ($in['password'] ?? '');

        if ($identifier === '' || $password === '') {
            $this->api->respond_error('Username/email and password are required.', 422);
        }

        // Basic brute-force protection: 10 attempts per minute per IP
        $this->api->rate_limit('login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 60);

        $user = $this->db->table('users')
            ->where('username', $identifier)
            ->or_where('email', strtolower($identifier))
            ->get();

        if (!$user || !password_verify($password, $user['password'])) {
            $this->api->respond_error('Invalid credentials.', 401);
        }
        if ((int) $user['is_active'] !== 1) {
            $this->api->respond_error('Account is disabled.', 403);
        }

        $tokens = $this->api->issue_tokens([
            'id'     => (int) $user['id'],
            'role'   => $user['role'],
            'scopes' => ['read', 'write'],
        ]);

        $this->api->respond([
            'message' => 'Login successful',
            'user'    => $this->public_user($user),
            'tokens'  => $tokens,
        ]);
    }

    /** POST /api/refresh  { refresh_token } */
    public function refresh()
    {
        $in = json_input();
        $token = (string) ($in['refresh_token'] ?? '');
        if ($token === '') {
            $this->api->respond_error('refresh_token is required.', 422);
        }
        $this->api->refresh_access_token($token); // responds + exits
    }

    /** POST /api/logout  { refresh_token } - revokes the refresh token */
    public function logout()
    {
        $in = json_input();
        $token = (string) ($in['refresh_token'] ?? '');
        if ($token !== '') {
            $this->api->revoke_refresh_token($token);
        }
        $this->api->respond(['message' => 'Logged out']);
    }

    /** GET /api/me  (protected) */
    public function me()
    {
        $payload = $this->api->require_jwt();
        $user = $this->db->table('users')->where('id', $payload['sub'])->get();
        if (!$user) {
            $this->api->respond_error('Unauthorized', 401);
        }
        $this->api->respond(['user' => $this->public_user($user)]);
    }

    private function public_user(array $u): array
    {
        return [
            'id'       => (int) $u['id'],
            'username' => $u['username'],
            'email'    => $u['email'],
            'role'     => $u['role'],
        ];
    }
}
