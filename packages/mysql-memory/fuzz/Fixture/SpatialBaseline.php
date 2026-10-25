<?php

declare(strict_types=1);

namespace Fuzz\Fixture;

use Fuzz\Target\ExecutionTarget;
use PDO;
use RuntimeException;

/**
 * Restores spatial reference definitions between independent differential inputs.
 *
 * Initial catalog data is fixture state. Every generated CREATE or DROP still runs
 * and its diagnostics are compared. Repair DDL is discovered on the reference and
 * applied to both servers; a failed repair aborts the campaign.
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
     * @var array<int, true> Systems changed by earlier observations on the reference
     */
    public array $changed = [];

    /**
     * Captures the initial catalog once. Older releases have no user-defined SRS catalog.
     *
     * @throws RuntimeException When the initial catalog cannot be read
     */
    public function __construct(PDO $native, public readonly string $version)
    {
        if (version_compare($version, '8.0.0', '<')) {
            return;
        }
        $rows = $native->query('SELECT SRS_ID, SRS_NAME, ORGANIZATION, ORGANIZATION_COORDSYS_ID, DEFINITION, DESCRIPTION FROM information_schema.ST_SPATIAL_REFERENCE_SYSTEMS');
        if ($rows === false) {
            throw new RuntimeException('Cannot capture the spatial reference catalog.');
        }
        /** @var list<array{int|string, string, ?string, int|string|null, string, ?string}> $catalog */
        $catalog = $rows->fetchAll(PDO::FETCH_NUM);
        foreach ($catalog as [$id, $name, $organization, $organizationId, $definition, $description]) {
            $sql = 'CREATE SPATIAL REFERENCE SYSTEM ' . $id . ' NAME ' . $native->quote($name);
            if ($organization !== null) {
                $sql .= ' ORGANIZATION ' . $native->quote($organization) . ' IDENTIFIED BY ' . $organizationId;
            }
            $sql .= ' DEFINITION ' . $native->quote($definition);
            if ($description !== null) {
                $sql .= ' DESCRIPTION ' . $native->quote($description);
            }
            $this->definitions[(int) $id] = $sql;
        }
        $this->signatures = $this->read($native);
    }

    /**
     * Reads compact signatures without transferring every WKT on every observation.
     *
     * @return array<int, string>
     * @throws RuntimeException When the catalog cannot be inspected
     */
    public function read(PDO $native): array
    {
        $rows = $native->query('SELECT SRS_ID, SHA2(CAST(JSON_ARRAY(SRS_NAME, ORGANIZATION, ORGANIZATION_COORDSYS_ID, DEFINITION, DESCRIPTION) AS CHAR), 256) FROM information_schema.ST_SPATIAL_REFERENCE_SYSTEMS');
        if ($rows === false) {
            throw new RuntimeException('Cannot inspect the spatial reference catalog.');
        }

        /** @var array<int, string> $signatures */
        $signatures = $rows->fetchAll(PDO::FETCH_KEY_PAIR);
        return $signatures;
    }

    /**
     * Discovers creations, replacements and removals, retaining repairs for the memory observation.
     *
     * @throws RuntimeException When catalog inspection fails
     */
    public function discover(PDO $native): void
    {
        if (version_compare($this->version, '8.0.0', '<')) {
            return;
        }
        $current = $this->read($native);
        foreach (array_unique([...array_keys($this->signatures), ...array_keys($current)]) as $id) {
            if (($this->signatures[$id] ?? null) !== ($current[$id] ?? null)) {
                $this->changed[$id] = true;
            }
        }
    }

    /**
     * Applies the same retained repair to either server, checking every DDL result.
     *
     * Removed fixture tables cannot keep an SRS in use during restoration. All changed
     * systems are dropped before any is recreated, so exchanged names do not collide.
     * @throws RuntimeException When a repair statement fails
     */
    public function restore(PDO $connection): void
    {
        if ($this->changed === []) {
            return;
        }
        $fixture = new Tables();
        $fixture->execute($connection, 'DROP DATABASE IF EXISTS `' . ExecutionTarget::DATABASE . '`');
        foreach ($this->changed as $id => $_) {
            $fixture->execute($connection, 'DROP SPATIAL REFERENCE SYSTEM IF EXISTS ' . $id);
        }
        foreach ($this->changed as $id => $_) {
            if (isset($this->definitions[$id])) {
                $fixture->execute($connection, $this->definitions[$id]);
            }
        }
    }
}
