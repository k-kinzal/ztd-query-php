<?php

declare(strict_types=1);

namespace Harbor\Security;

use Harbor\Http\Problem;

final class Session
{
    private array $data;

    public function __construct(array &$data, private readonly bool $native = false)
    {
        $this->data = &$data;
        $this->data['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function start(bool $secure): self
    {
        ini_set('session.use_strict_mode', '1');
        session_name('harbor_session');
        session_set_cookie_params(['httponly' => true, 'secure' => $secure, 'samesite' => 'Lax', 'path' => '/']);
        session_start();
        return new self($_SESSION, true);
    }

    public function user(): ?int
    {
        return $this->data['user'] ?? null;
    }
    public function csrf(): string
    {
        return $this->data['csrf'];
    }

    public function requireUser(): int
    {
        return $this->user() ?? throw new Problem(401, 'Sign in first.');
    }

    public function verify(string $token): void
    {
        if (!hash_equals($this->csrf(), $token)) {
            throw new Problem(403, 'The CSRF token is missing or expired.');
        }
    }

    public function login(int $user): void
    {
        if ($this->native) {
            session_regenerate_id(true);
        }
        $this->data = ['user' => $user, 'csrf' => bin2hex(random_bytes(32))];
    }

    public function logout(): void
    {
        if ($this->native) {
            session_regenerate_id(true);
        }
        $this->data = ['csrf' => bin2hex(random_bytes(32))];
    }
}
