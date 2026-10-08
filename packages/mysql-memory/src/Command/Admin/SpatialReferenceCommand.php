<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\DataError;
use MySqlMemory\Error\SchemaError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\CreateSpatialReference;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\DropSpatialReference;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind;
use SqlSemantics\Statement\Operation;

/**
 * Executes CREATE SPATIAL REFERENCE SYSTEM and DROP SPATIAL REFERENCE SYSTEM.
 *
 * Each commits the open transaction. CREATE refuses an SRID in use unless OR REPLACE is written
 * (ER_SRS_ID_ALREADY_EXISTS; a warning under IF NOT EXISTS, which ends the statement), then a
 * definition it cannot parse (ER_SRS_PARSE_ERROR), then a name another system has
 * (ER_DUP_ENTRY). DROP refuses an SRID no system has (ER_SRS_NOT_FOUND; a warning under IF
 * EXISTS). Creating or dropping a system in a reserved range warns that the range is reserved
 * (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-spatial-reference-system.html,
 * https://dev.mysql.com/doc/refman/8.4/en/drop-spatial-reference-system.html.
 *
 * @visibility MySqlMemory
 */
final class SpatialReferenceCommand implements Command
{
    /**
     * The SRID ranges reserved for the systems of the server and of later releases.
     */
    public const RESERVED = [[0, 32767], [60000000, 69999999], [2000000000, 2147483647]];

    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Creates, replaces or drops the system.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        $session->transaction->commit();
        if ($statement instanceof CreateSpatialReference) {
            $this->create($statement, $session);
        }
        if ($statement instanceof DropSpatialReference) {
            $this->drop($statement, $session);
        }

        return new Completion(0, 0, $session->diagnostics->count());
    }

    /**
     * Creates or replaces a system; under IF NOT EXISTS an SRID in use only warns.
     *
     * @throws \MySqlMemory\Error\SqlError When the SRID is in use, the definition does not parse, or another system has the name
     */
    public function create(CreateSpatialReference $statement, Session $session): void
    {
        $systems = $session->instance->registry->spatialCatalog;
        $srid = (int) (new Literals())->number($statement->srid);
        if (!$statement->orReplace && $systems->name($srid) !== null) {
            if (!$statement->ifNotExists) {
                throw SchemaError::SrsExists->error((string) $srid);
            }
            $session->diagnostics->warning(SchemaError::SrsExistsWarning, SchemaError::SrsExistsWarning->message((string) $srid));

            return;
        }
        $name = '';
        $definition = '';
        foreach ($statement->attributes as $attribute) {
            if ($attribute->kind === SpatialAttributeKind::Name) {
                $name = (new Literals())->bytes($attribute->value);
            }
            if ($attribute->kind === SpatialAttributeKind::Definition) {
                $definition = (new Literals())->bytes($attribute->value);
            }
        }
        if (!(new SpatialDefinition())->valid($definition)) {
            throw SchemaError::SrsParseError->error((string) $srid);
        }
        if ($systems->named($name, $srid) !== null) {
            throw DataError::DuplicateEntry->error('1-' . $name, 'st_spatial_reference_systems.SRS_NAME');
        }
        $systems->define($srid, $name);
        $this->reserved($srid, $session);
    }

    /**
     * Drops a system; under IF EXISTS an SRID no system has only warns.
     *
     * @throws \MySqlMemory\Error\SqlError When no system has the SRID
     */
    public function drop(DropSpatialReference $statement, Session $session): void
    {
        $systems = $session->instance->registry->spatialCatalog;
        $srid = (int) (new Literals())->number($statement->srid);
        if ($systems->name($srid) === null) {
            if (!$statement->ifExists) {
                throw SchemaError::SrsNotFound->error((string) $srid);
            }
            $session->diagnostics->warning(SchemaError::SrsNotFoundWarning, SchemaError::SrsNotFoundWarning->message((string) $srid));

            return;
        }
        $systems->drop($srid);
        $this->reserved($srid, $session);
    }
    /**
     * Warns that an SRID lies in a reserved range.
     */
    public function reserved(int $srid, Session $session): void
    {
        foreach (self::RESERVED as [$low, $high]) {
            if ($srid >= $low && $srid <= $high) {
                $session->diagnostics->warning(SchemaError::SrsReservedRange, SchemaError::SrsReservedRange->message((string) $low, (string) $high));
            }
        }
    }
}
