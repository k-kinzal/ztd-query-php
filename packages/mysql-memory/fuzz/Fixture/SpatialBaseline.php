<?php

declare(strict_types=1);

namespace Fuzz\Fixture;

use Fuzz\Observation\Observer;
use Fuzz\Observation\Record;
use Fuzz\Target\ExecutionTarget;
use PDO;
use RuntimeException;

/**
 * Restores spatial reference definitions between independent differential inputs.
 *
 * Initial catalog data is fixture state. Every generated CREATE or DROP still runs
 * and its diagnostics are compared. Each server is inspected independently;
 * a failed read or repair aborts the campaign.
 */
final class SpatialBaseline
{
    /**
     * @var array<int, string> The original definitions as ordinary CREATE statements
     */
    public array $definitions = [];

    /**
     * @var array<int, string> The original signatures of all catalog attributes
     */
    public array $signatures = [];

    /**
     * @var array<int, true> Systems changed on the connection being restored
     */
    public array $changed = [];

    /**
     * Captures the initial catalog once. Older releases have no user-defined SRS catalog.
     *
     * @throws RuntimeException When the initial catalog cannot be read
     */
    public function __construct(PDO $native, public readonly string $version, ?Record $record = null)
    {
        if (version_compare($version, '8.0.0', '<')) {
            return;
        }
        $catalog = (new Observer())->rows($native, 'SELECT SRS_ID, SRS_NAME, ORGANIZATION, ORGANIZATION_COORDSYS_ID, DEFINITION, DESCRIPTION FROM information_schema.ST_SPATIAL_REFERENCE_SYSTEMS', $record);
        foreach ($catalog as $row) {
            if (!is_array($row) || !array_is_list($row) || count($row) !== 6) {
                throw new RuntimeException('The spatial reference catalog contains an invalid row.');
            }
            [$id, $name, $organization, $organizationId, $definition, $description] = $row;
            $id = $this->identity($id);
            if (array_key_exists($id, $this->definitions) || !is_string($name) || !is_string($definition) || ($organization !== null && !is_string($organization)) || ($description !== null && !is_string($description)) || (($organization === null) !== ($organizationId === null))) {
                throw new RuntimeException('The spatial reference catalog contains duplicate identities or invalid attributes.');
            }
            $sql = 'CREATE SPATIAL REFERENCE SYSTEM ' . $id . ' NAME ' . $native->quote($name);
            if ($organization !== null) {
                $sql .= ' ORGANIZATION ' . $native->quote($organization) . ' IDENTIFIED BY ' . $this->identity($organizationId);
            }
            $sql .= ' DEFINITION ' . $native->quote($definition);
            if ($description !== null) {
                $sql .= ' DESCRIPTION ' . $native->quote($description);
            }
            $this->definitions[$id] = $sql;
        }
        $this->signatures = $this->read($native, $record);
    }

    /**
     * Reads compact signatures without transferring every WKT on every observation.
     *
     * @return array<int, string>
     * @throws RuntimeException When the catalog cannot be inspected
     */
    public function read(PDO $native, ?Record $record = null): array
    {
        $rows = (new Observer())->rows($native, 'SELECT SRS_ID, SHA2(CAST(JSON_ARRAY(SRS_NAME, ORGANIZATION, ORGANIZATION_COORDSYS_ID, DEFINITION, DESCRIPTION) AS CHAR), 256) FROM information_schema.ST_SPATIAL_REFERENCE_SYSTEMS', $record);
        $signatures = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_is_list($row) || count($row) !== 2 || !is_string($row[1]) || preg_match('/\A[0-9a-f]{64}\z/', $row[1]) !== 1) {
                throw new RuntimeException('The spatial reference signature inventory contains an invalid row.');
            }
            $id = $this->identity($row[0]);
            if (array_key_exists($id, $signatures)) {
                throw new RuntimeException('The spatial reference signature inventory contains a duplicate identity.');
            }
            $signatures[$id] = $row[1];
        }
        return $signatures;
    }

    /**
     * Validates the unsigned 32-bit identity shared by SRIDs and organization identifiers.
     *
     * @throws RuntimeException When the catalog identity cannot be represented without coercion
     */
    public function identity(mixed $value): int
    {
        if (!is_int($value) && !is_string($value)) {
            throw new RuntimeException('The spatial reference identity has an invalid type.');
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 4294967295]]);
        if (!is_int($id)) {
            throw new RuntimeException('The spatial reference identity is outside the unsigned 32-bit domain.');
        }
        return $id;
    }

    /**
     * Discovers creations, replacements and removals on the connection being restored.
     *
     * @throws RuntimeException When catalog inspection fails
     */
    public function discover(PDO $native, ?Record $record = null): void
    {
        $this->changed = [];
        if (version_compare($this->version, '8.0.0', '<')) {
            return;
        }
        $current = $this->read($native, $record);
        foreach (array_unique([...array_keys($this->signatures), ...array_keys($current)]) as $id) {
            if (($this->signatures[$id] ?? null) !== ($current[$id] ?? null)) {
                $this->changed[$id] = true;
            }
        }
    }

    /**
     * Restores the discovered differences to the captured fixture, checking every DDL result.
     *
     * Removed fixture tables cannot keep an SRS in use during restoration. All changed
     * systems are dropped before any is recreated, so exchanged names do not collide.
     * @throws RuntimeException When a repair statement fails
     */
    public function restore(PDO $connection, ?Record $record = null): void
    {
        if ($this->changed === []) {
            return;
        }
        $fixture = new Tables();
        $fixture->execute($connection, 'DROP DATABASE IF EXISTS `' . ExecutionTarget::DATABASE . '`', $record);
        foreach ($this->changed as $id => $_) {
            $fixture->execute($connection, 'DROP SPATIAL REFERENCE SYSTEM IF EXISTS ' . $id, $record);
        }
        foreach ($this->changed as $id => $_) {
            if (isset($this->definitions[$id])) {
                $fixture->execute($connection, $this->definitions[$id], $record);
            }
        }
    }
}
