<?php

declare(strict_types=1);

namespace Fuzz\Target;

use PDO;

/**
 * Validates a random primary password returned for the current account.
 *
 * Only an isolated ALTER USER or SET PASSWORD is eligible. The returned
 * account, factor and configured length must be correct. A subsequent SET PASSWORD must
 * reject a different current password and accept the returned one. Only then is the random
 * text interchangeable; metadata, warnings, errors and fixture rows remain exact.
 * RETAIN additionally verifies the previous primary hash becomes the secondary, survives an
 * ordinary update, and is removed by DISCARD without changing the primary. These checks cover
 * SQL password storage and replacement, not wire authentication or entropy.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/password-management.html.
 */
final class RandomPasswords
{
    /**
     * The contract recorded only after length and password replacement are verified.
     */
    public const CONTRACT = 'random-primary-password-length-and-replacement';

    /**
     * @param int $length The session's configured generation length before the statement
     * @param string $account CURRENT_USER() before the statement
     * @param Password\Retention|null $retention The previous primary when retention is requested
     */
    public function __construct(public readonly int $length, public readonly string $account, public readonly ?Password\Retention $retention = null)
    {
    }

    /**
     * Selects only a single change of the current account's primary password.
     */
    public static function handles(string $sql, string $version): bool
    {
        $user = '(?:CURRENT_USER(?:\s*\(\s*\))?|USER\s*\(\s*\))';

        return !str_starts_with($version, '5.') && preg_match('/\A\s*(?:ALTER\s+USER\s+' . $user . '\s+IDENTIFIED\s+BY\s+RANDOM\s+PASSWORD|SET\s+PASSWORD(?:\s+FOR\s+' . $user . ')?\s+TO\s+RANDOM)(?:\s+RETAIN\s+CURRENT\s+PASSWORD)?\s*;?\s*\z/i', $sql) === 1;
    }

    /**
     * Samples the account and length independently of the generated result.
     */
    public static function capture(PDO $pdo, bool $retain = false): ?self
    {
        $statement = $pdo->query('SELECT @@generated_random_password_length, CURRENT_USER()');
        $row = $statement === false ? false : $statement->fetch(PDO::FETCH_NUM);
        $statement = null;
        $retention = $retain ? Password\Retention::capture($pdo) : null;
        if ($retain && $retention === null) {
            return null;
        }

        return is_array($row) && is_numeric($row[0] ?? null) && is_string($row[1] ?? null) ? new self((int) $row[0], $row[1], $retention) : null;
    }

    /**
     * Extracts only a well-formed password for the sampled account and factor.
     *
     * @param array<string, mixed> $observation The complete original observation
     */
    public function password(array $observation): ?string
    {
        $results = $observation['results'] ?? null;
        if (isset($observation['error']) || !is_array($results) || count($results) !== 1 || !is_array($results[0] ?? null)) {
            return null;
        }
        $columns = $results[0]['columns'] ?? null;
        $rows = $results[0]['rows'] ?? null;
        if (!is_array($columns) || count($columns) !== 4 || !is_array($rows) || count($rows) !== 1 || !is_array($rows[0] ?? null) || count($rows[0]) !== 4) {
            return null;
        }
        foreach (['user', 'host', 'generated password', 'auth_factor'] as $index => $name) {
            if (!is_array($columns[$index] ?? null) || ($columns[$index][0] ?? null) !== $name || ($columns[$index][1] ?? null) !== '') {
                return null;
            }
        }
        $row = $rows[0];
        if (!is_string($row[0] ?? null) || !is_string($row[1] ?? null) || $row[0] . '@' . $row[1] !== $this->account || !in_array($row[3] ?? null, [1, '1'], true)) {
            return null;
        }
        $password = $row[2] ?? null;

        return $this->length >= 5 && $this->length <= 255 && is_string($password) && strlen($password) === $this->length && preg_match('/\A[!-~]+\z/', $password) === 1 ? $password : null;
    }

    /**
     * Requires rejection of a wrong current password and acceptance of the returned password.
     * The accepted update keeps the same primary password; original observations are already recorded.
     */
    public function verifies(PDO $pdo, string $password): bool
    {
        $observer = new Observer();
        $prefix = 'SET PASSWORD = ' . $pdo->quote($password) . ' REPLACE ';
        $wrong = $observer->observe($pdo, $prefix . $pdo->quote($password . '!'), false);
        if (($wrong['error'] ?? null) !== [3891, 'HY000', 'Incorrect current password. Specify the correct password which has to be replaced.']) {
            return false;
        }
        $right = $observer->observe($pdo, $prefix . $pdo->quote($password), false);

        return $right === ['results' => [['affected' => 0, 'lastInsertId' => '0']], 'warnings' => []];
    }

    /**
     * @param array<string, mixed> $observation The complete original observation
     * @return array<string, mixed> Only a validated password is replaced by the recorded contract
     */
    public function comparable(PDO $pdo, array $observation): array
    {
        $password = $this->password($observation);
        if ($password === null || ($this->retention !== null && !$this->retention->retained($pdo)) || !$this->verifies($pdo, $password) || ($this->retention !== null && !$this->retention->discarded($pdo))) {
            return $observation;
        }
        $results = $observation['results'];
        assert(is_array($results) && is_array($results[0]) && is_array($results[0]['rows']) && is_array($results[0]['rows'][0]));
        $results[0]['rows'][0][2] = '{' . self::CONTRACT . '}';
        $observation['results'] = $results;
        $observation['contracts'] = $this->retention === null ? [self::CONTRACT] : [self::CONTRACT, Password\Retention::CONTRACT];

        return $observation;
    }
}
