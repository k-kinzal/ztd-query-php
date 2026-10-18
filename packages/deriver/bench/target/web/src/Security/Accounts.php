<?php

declare(strict_types=1);

namespace Harbor\Security;

use Harbor\Audit\AuditLog;
use Harbor\Database\Connection;
use Harbor\Http\Problem;
use Harbor\Validation\Input;

final class Accounts
{
    public function __construct(private readonly Connection $database, private readonly AuditLog $audit, private readonly RateLimiter $limiter)
    {
    }

    public function register(array $input): int
    {
        $email = strtolower(Input::text($input, 'email', 3, 254));
        $name = Input::text($input, 'name', 2, 80);
        $password = Input::text($input, 'password', 12, 200);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Problem(422, 'A valid email address is required.');
        }
        return $this->database->transaction(function () use ($email, $name, $password): int {
            if ($this->database->one('SELECT id FROM users WHERE email = ?', [$email]) !== null) {
                throw new Problem(409, 'This address is already registered.');
            }
            $this->database->execute('INSERT INTO users(email,name,password,created_at) VALUES (?,?,?,?)', [$email, $name, password_hash($password, PASSWORD_DEFAULT), time()]);
            $id = (int) $this->database->pdo->lastInsertId();
            $this->audit->record($id, null, 'account.registered', (string) $id);
            return $id;
        });
    }

    public function authenticate(string $email, string $password, string $ip): int
    {
        $this->limiter->consume('login:' . hash('sha256', $ip), 20, 900);
        $user = $this->database->one('SELECT * FROM users WHERE email = ?', [strtolower(trim($email))]);
        // A valid fixed hash makes an unknown account follow the password verification path.
        $valid = password_verify($password, $user['password'] ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (!$valid || $user === null || $user['status'] !== 'active') {
            throw new Problem(401, 'Invalid credentials.');
        }
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $this->database->execute('UPDATE users SET password = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        return (int) $user['id'];
    }

    public function changePassword(int $user, string $current, string $replacement): void
    {
        $account = $this->database->one('SELECT * FROM users WHERE id = ?', [$user]);
        if ($account === null || !password_verify($current, $account['password'])) {
            throw new Problem(403, 'The current password is incorrect.');
        }
        Input::text(['password' => $replacement], 'password', 12, 200);
        $this->database->execute('UPDATE users SET password = ? WHERE id = ?', [password_hash($replacement, PASSWORD_DEFAULT), $user]);
        $this->audit->record($user, null, 'account.password_changed', (string) $user);
    }
}
