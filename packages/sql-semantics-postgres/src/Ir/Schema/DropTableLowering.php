<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Drop as ParsedDrop;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;

/**
 * Resolves the absolute identities of tables before publishing their removal.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class DropTableLowering
{
    /**
     * Publishes a persistent table removal with the native dependency policy.
     *
     * @throws InvalidConstruction When a target cannot be resolved in the supported native profile
     */
    public function lower(BoundStatement $operation, ParsedDrop $statement): DropTable
    {
        $context = $operation->context;
        $profile = $context->profile;
        Check::input($profile->parameters === ParameterStyle::Native && $context->relationNames === Comparison::Sensitive, 'DROP TABLE requires the default native profile.');
        Check::input($statement->kind === ObjectKind::Table, 'The complete DROP requires the TABLE object kind.');
        $schemas = [$context->declarationSchema->value => true];
        foreach ($context->tables as $table) {
            $schemas[($table->name->schema ?? $context->declarationSchema)->value] = true;
        }
        $targets = [];
        foreach ($statement->objects as $target) {
            Check::input($target instanceof DottedName && count($target->parts) <= 2, 'DROP TABLE needs an ordinary table identity.');
            $resolution = $operation->facts->relation($target)->table;
            if ($resolution instanceof DeclaredTable && $resolution->table->kind === RelationKind::BaseTable) {
                $name = $resolution->table->name;
                $schema = ($name->schema ?? $context->declarationSchema)->value;
            } elseif ($statement->ifExists && $resolution === null) {
                $name = $target->qualified();
                Check::input($name !== null && $name->schema !== null && isset($schemas[$name->schema->value]), 'Optional DROP TABLE needs a known explicit schema.');
                $schema = $name->schema->value;
            } else {
                throw new InvalidConstruction('DROP TABLE requires an ordinary table or a known schema-qualified absence.');
            }
            Check::input($name->catalog === null, 'DROP TABLE needs an absolute table identity.');
            $targets[] = new TableName(new Identifier($schema), new Identifier($name->name->value));
        }

        return new DropTable($targets, $statement->ifExists, $statement->behavior === DropBehavior::Cascade, $profile->grammar);
    }
}
