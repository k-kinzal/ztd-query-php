<?php

declare(strict_types=1);

namespace MySqlMemory\Account;

use SqlSemantics\Platform\MySql\Statement\Account\Option\TlsKind;

/**
 * An account or a role of the server: its authentication, password and lock settings, its TLS requirement, resource limits, attributes and privileges.
 *
 * A role is an account created locked, with an expired password and no authentication string. A
 * password lifetime of null follows default_password_lifetime and 0 never expires; a password
 * history or reuse interval of null, and a current-password requirement of null, follow the
 * global settings. A lock time of -1 is UNBOUNDED. The password set is kept in clear text where
 * it is known, so that REPLACE can be checked; the authentication string the server would store
 * is generated for it.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html,
 * https://dev.mysql.com/doc/refman/8.4/en/password-management.html.
 *
 * @visibility MySqlMemory
 */
final class Account
{
    /**
     * @var array{string, string|null}|null The retained authentication string and known password, or null without a secondary password
     */
    public ?array $secondary = null;

    /**
     * @param Identity $identity The user name and host
     * @param string $plugin The authentication plugin
     * @param string $hash The authentication string
     * @param string|null $password The password in clear text, or null when it is not known
     * @param bool $expired Whether the password is expired
     * @param int|null $lifetime The password lifetime in days, 0 for never, null for the default
     * @param bool $locked Whether the account is locked
     * @param int|null $history The password history length, null for the default
     * @param int|null $reuse The password reuse interval in days, null for the default
     * @param bool|null $requireCurrent Whether a password change requires the current password, null for the default
     * @param int $failedAttempts The failed login attempts that lock the account, 0 for none
     * @param int $lockTime The days a failed login locks the account, -1 for unbounded
     * @param TlsKind $tls The kind of TLS connection required
     * @param array<string, string> $tlsConditions The SUBJECT, ISSUER and CIPHER required, by name
     * @param array<string, int> $limits The resource limits, by option name
     * @param string|null $attributes The user attributes as the server writes the JSON object, or null for none
     * @param Grants $grants The privileges
     */
    public function __construct(
        public Identity $identity,
        public string $plugin = 'caching_sha2_password',
        public string $hash = '',
        public ?string $password = '',
        public bool $expired = false,
        public ?int $lifetime = null,
        public bool $locked = false,
        public ?int $history = null,
        public ?int $reuse = null,
        public ?bool $requireCurrent = null,
        public int $failedAttempts = 0,
        public int $lockTime = 0,
        public TlsKind $tls = TlsKind::None,
        public array $tlsConditions = [],
        public array $limits = ['MAX_QUERIES_PER_HOUR' => 0, 'MAX_UPDATES_PER_HOUR' => 0, 'MAX_CONNECTIONS_PER_HOUR' => 0, 'MAX_USER_CONNECTIONS' => 0],
        public ?string $attributes = null,
        public Grants $grants = new Grants(),
    ) {
    }

    /**
     * Answers a role, as CREATE ROLE creates it.
     */
    public static function role(Identity $identity): self
    {
        return new self($identity, expired: true, locked: true);
    }

    /**
     * Answers a copy that changes apart from this one.
     */
    public function copy(): self
    {
        $copy = clone $this;
        $copy->grants = $this->grants->copy();

        return $copy;
    }
}
