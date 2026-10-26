<?php

declare(strict_types=1);

namespace Fuzz\Observation;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Observes session effects after primary results and conditions have been retained.
 *
 * Transaction status comes from the driver's last server-status packet, before
 * state probes issue further SQL. Database selection and every exposed session
 * variable are retained. Only independently validated server-allocated statement
 * and connection identities receive the existing identity contract.
 */
final class SessionState
{
    /**
     * Reads session state without interpreting the statement through SQL Semantics.
     *
     * @return array{raw: array<string, mixed>, comparable: array<string, mixed>}
     * @throws RuntimeException When the session state cannot be observed
     */
    public function capture(PDO $connection, string $version): array
    {
        $emulate = $connection->getAttribute(PDO::ATTR_EMULATE_PREPARES);
        try {
            $transaction = $connection->inTransaction();
            $connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            $statement = $connection->query('SELECT DATABASE()');
            $database = $statement === false ? false : $statement->fetchColumn();
            if ($database !== null && !is_string($database)) {
                throw new RuntimeException('Cannot read the observed session database.');
            }
            $identities = StatementIdentities::capture($connection, $version);
            $variables = (new Observer())->probe($connection, 'SHOW SESSION VARIABLES');
            $raw = ['transaction' => $transaction, 'database' => $database, 'variables' => $variables];
            $comparable = $raw;
            $comparable['variables'] = $identities?->comparable($connection, $variables) ?? $variables;

            return ['raw' => $raw, 'comparable' => $comparable];
        } catch (PDOException $failure) {
            throw new RuntimeException('Cannot read observed session state.', 0, $failure);
        } finally {
            $connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulate);
        }
    }
}
