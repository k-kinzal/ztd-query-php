<?php

declare(strict_types=1);

namespace Fuzz\Target\Password;

use Fuzz\Target\Observer;
use PDO;

/**
 * Verifies a secondary password against the primary hash sampled before a random replacement.
 *
 * Each server must store its own exact previous primary hash, preserve it through an ordinary
 * primary update, then remove it on DISCARD without changing the new primary. Hash contents
 * are never copied between servers. These checks cover SQL state, not wire authentication.
 */
final class Retention
{
    /**
     * The additional contract recorded after retention and discard are verified.
     */
    public const CONTRACT = 'random-password-retains-primary-and-discards-secondary';

    /**
     * @param string $primary The nonempty primary hash before the generated statement
     */
    public function __construct(public readonly string $primary)
    {
    }

    /**
     * Captures the current account's nonempty primary authentication string.
     *
     * @phpstan-impure
     */
    public static function capture(PDO $pdo): ?self
    {
        $row = self::read($pdo);

        return $row !== null && $row[0] !== '' ? new self($row[0]) : null;
    }

    /**
     * Reads only the current account's primary and secondary hashes, rejecting ambiguous results.
     *
     * @return array{string, string|null}|null
     * @phpstan-impure
     */
    public static function read(PDO $pdo): ?array
    {
        $statement = $pdo->query("SELECT authentication_string, JSON_UNQUOTE(JSON_EXTRACT(User_attributes,'$.additional_password')) FROM mysql.user WHERE CONCAT(User,'@',Host)=CURRENT_USER()");
        $rows = $statement === false ? [] : $statement->fetchAll(PDO::FETCH_NUM);
        $row = $rows[0] ?? null;

        return count($rows) === 1 && is_array($row) && count($row) === 2 && is_string($row[0]) && (is_string($row[1]) || $row[1] === null) ? [$row[0], $row[1]] : null;
    }

    /**
     * Requires the old primary hash to be present as the secondary.
     *
     * @phpstan-impure
     */
    public function retained(PDO $pdo): bool
    {
        $row = self::read($pdo);

        return $this->primary !== '' && $row !== null && $row[0] !== '' && $row[1] === $this->primary;
    }

    /**
     * Requires successful discard to remove only the secondary credential.
     *
     * @phpstan-impure
     */
    public function discarded(PDO $pdo): bool
    {
        $before = self::read($pdo);
        if (!$this->retained($pdo)) {
            return false;
        }
        $result = (new Observer())->observe($pdo, 'ALTER USER CURRENT_USER DISCARD OLD PASSWORD', false);
        $after = self::read($pdo);

        return $result === ['results' => [['affected' => 0, 'lastInsertId' => '0']], 'warnings' => []] && $before !== null && $after === [$before[0], null];
    }
}
