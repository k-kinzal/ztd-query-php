<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Alter;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\RelationTarget;

/**
 * Resolves a persistent rename coordinate and the optional declaration for checking its members.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class RenameTargetLowering
{
    /**
     * Shares the scope check between both rename roles.
     *
     * @return array{TableName, Table|null}
     */
    public function lower(BoundStatement $operation, RelationTarget $target, bool $ifExists): array
    {
        $context = $operation->context;
        $reference = $target->relation->name;
        $resolution = $operation->facts->relation($target)->table;
        Check::input($reference->catalog === null, 'A table rename needs an absolute schema and relation.');
        if ($resolution instanceof DeclaredTable) {
            $table = $resolution->table;
            Check::input(
                $table->kind === RelationKind::BaseTable && $table->name->catalog === null,
                'ALTER TABLE RENAME requires a persistent base table.'
            );
            $tableSchema = ($table->name->schema ?? $context->declarationSchema)->value;

            return [new TableName(new Identifier($tableSchema), new Identifier($table->name->name->value)), $table];
        }
        Check::input($ifExists && $context->complete && $resolution === null, 'An optional rename requires known absence in a complete catalog.');
        $schemas = [$context->declarationSchema->value => true];
        foreach ($context->tables as $table) {
            $schemas[($table->name->schema ?? $context->declarationSchema)->value] = true;
        }
        Check::input(
            $reference->schema !== null && isset($schemas[$reference->schema->value]),
            'An optional rename requires a known explicit schema.'
        );

        return [new TableName(new Identifier($reference->schema->value), new Identifier($reference->name->value)), null];
    }
}
