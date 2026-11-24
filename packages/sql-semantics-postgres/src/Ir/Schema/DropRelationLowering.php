<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\DropIndex;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\DropIndexLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\DropSchema;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\DropSchemaLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\DropSequence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\DropSequenceLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\DropView;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\DropViewLowering;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Drop;

/**
 * Dispatches persistent table and view removals by their native object kind.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class DropRelationLowering
{
    /**
     * Publishes the owning checked relation removal.
     * @throws InvalidConstruction When the statement has no supported relation kind
     */
    public function lower(BoundStatement $operation, Drop $statement): DropIndex|DropSchema|DropSequence|DropTable|DropView
    {
        return match ($statement->kind) {
            ObjectKind::Index => (new DropIndexLowering())->lower($operation, $statement),
            ObjectKind::Schema => (new DropSchemaLowering())->lower($operation, $statement),
            ObjectKind::Sequence => (new DropSequenceLowering())->lower($operation, $statement),
            ObjectKind::Table => (new DropTableLowering())->lower($operation, $statement),
            ObjectKind::View, ObjectKind::MaterializedView => (new DropViewLowering())->lower($operation, $statement),
            default => throw new InvalidConstruction('The complete DROP requires a supported persistent relation kind.'),
        };
    }
}
