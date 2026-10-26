<?php

declare(strict_types=1);

namespace Fuzz\Observation\Password;

use Fuzz\Observation\Observer;
use Fuzz\Observation\Record;
use PDO;
use RuntimeException;

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
     * @param string $primary The primary hash before the generated statement, including an account without a password
     */
    public function __construct(public readonly string $primary)
    {
    }

    /**
     * Captures the current account's primary authentication string without treating an empty credential as a read failure.
     *
     * @phpstan-impure
     */
    public static function capture(PDO $pdo, ?Record $record = null): self
    {
        return new self(self::read($pdo, $record)[0]);
    }

    /**
     * Reads only the current account's primary and secondary hashes, rejecting ambiguous results.
     *
     * @return array{string, string|null}
     * @phpstan-impure
     * @throws RuntimeException When the account sample is unreadable, missing or ambiguous
     */
    public static function read(PDO $pdo, ?Record $record = null): array
    {
        $rows = (new Observer())->rows($pdo, "SELECT authentication_string, JSON_UNQUOTE(JSON_EXTRACT(User_attributes,'$.additional_password')) FROM mysql.user WHERE CONCAT(User,'@',Host)=CURRENT_USER()", $record);
        $row = $rows[0] ?? null;
        if (count($rows) !== 1 || !is_array($row) || count($row) !== 2 || !is_string($row[0]) || (!is_string($row[1]) && $row[1] !== null)) {
            throw new RuntimeException('Cannot read exactly one primary and secondary credential sample.');
        }
        return [$row[0], $row[1]];
    }

    /**
     * Requires the old primary hash to be present as the secondary.
     *
     * @phpstan-impure
     */
    public function retained(PDO $pdo, ?Record $record = null): bool
    {
        $row = self::read($pdo, $record);

        return $this->primary !== '' && $row[0] !== '' && $row[1] === $this->primary;
    }

    /**
     * Requires successful discard to remove only the secondary credential.
     *
     * @phpstan-impure
     */
    public function discarded(PDO $pdo, ?Record $record = null): bool
    {
        $before = self::read($pdo, $record);
        if (!$this->retained($pdo, $record)) {
            return false;
        }
        $sql = 'ALTER USER CURRENT_USER DISCARD OLD PASSWORD';
        $result = (new Observer())->observe($pdo, $sql, false, $record?->probe($sql));
        $after = self::read($pdo, $record);

        return $result === ['results' => [['affected' => 0, 'lastInsertId' => '0']], 'warnings' => []] && $after === [$before[0], null];
    }
}
