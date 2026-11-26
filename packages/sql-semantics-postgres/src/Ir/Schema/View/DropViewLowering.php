<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\View;

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
 * Resolves persistent view identities and erases the analysis context.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class DropViewLowering
{
    /**
     * Publishes a checked ordinary or materialized view removal.
     * @throws InvalidConstruction When the selected kind or exact targets cannot be resolved
     */
    public function lower(BoundStatement $operation, ParsedDrop $statement): DropView
    {
        $context = $operation->context;
        $profile = $context->profile;
        Check::input($profile->parameters === ParameterStyle::Native && $context->relationNames === Comparison::Sensitive, 'DROP VIEW requires the native profile.');
        [$kind, $declaredKind] = match ($statement->kind) {
            ObjectKind::View => [ViewRemovalKind::Ordinary, RelationKind::View],
            ObjectKind::MaterializedView => [ViewRemovalKind::Materialized, RelationKind::MaterializedView],
            default => throw new InvalidConstruction('DROP VIEW requires a view kind.'),
        };
        $schemas = [$context->declarationSchema->value => true];
        foreach ($context->tables as $table) {
            $schemas[($table->name->schema ?? $context->declarationSchema)->value] = true;
        }
        $targets = [];
        foreach ($statement->objects as $target) {
            Check::input($target instanceof DottedName && count($target->parts) <= 2, 'DROP VIEW needs an ordinary schema and view name.');
            $resolution = $operation->facts->relation($target)->table;
            if ($resolution instanceof DeclaredTable) {
                Check::input($resolution->table->kind === $declaredKind, 'DROP VIEW requires a target of the requested view kind.');
                $name = $resolution->table->name;
                $schema = ($name->schema ?? $context->declarationSchema)->value;
            } else {
                Check::input($statement->ifExists && $context->complete && $resolution === null, 'Optional DROP VIEW needs a known absence in a complete catalog.');
                $name = $target->qualified();
                Check::input($name !== null && $name->schema !== null && isset($schemas[$name->schema->value]), 'Optional DROP VIEW needs a known explicit schema.');
                $schema = $name->schema->value;
            }
            Check::input($name->catalog === null, 'DROP VIEW needs an absolute view identity.');
            $targets[] = new ViewName(new Identifier($schema), new Identifier($name->name->value));
        }

        return new DropView($kind, $targets, $statement->ifExists, $statement->behavior === DropBehavior::Cascade, $profile->grammar);
    }
}
