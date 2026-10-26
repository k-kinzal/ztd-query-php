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
    public function capture(PDO $connection, string $version, ?Record $record = null): array
    {
        $emulate = $connection->getAttribute(PDO::ATTR_EMULATE_PREPARES);
        try {
            $transaction = $connection->inTransaction();
            $raw = ['transaction' => $transaction];
            if ($record !== null) {
                $record->raw['session'] = $raw;
            }
            $connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            $observer = new Observer();
            $databases = $observer->rows($connection, 'SELECT DATABASE()', $record);
            if (count($databases) !== 1 || !is_array($databases[0]) || count($databases[0]) !== 1 || (!is_string($databases[0][0]) && $databases[0][0] !== null)) {
                throw new RuntimeException('Cannot read the observed session database.');
            }
            $raw['database'] = $databases[0][0];
            if ($record !== null) {
                $record->raw['session'] = $raw;
            }
            $identities = StatementIdentities::capture($connection, $version, $record);
            $variables = $observer->probe($connection, 'SHOW SESSION VARIABLES', $record);
            $raw['variables'] = $variables;
            if ($record !== null) {
                $record->raw['session'] = $raw;
            }
            $comparable = $raw;
            $comparable['variables'] = $identities->comparable($connection, $variables, $record);

            return ['raw' => $raw, 'comparable' => $comparable];
        } catch (PDOException $failure) {
            throw new RuntimeException('Cannot read observed session state.', 0, $failure);
        } finally {
            $connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulate);
        }
    }
}
