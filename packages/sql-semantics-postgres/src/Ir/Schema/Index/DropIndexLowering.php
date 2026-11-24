<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index;

use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Drop as ParsedDrop;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;

/**
 * Binds DROP INDEX in the relation namespace and discharges the catalog before publication.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class DropIndexLowering
{
    /**
     * Resolves every selector under the context's exact PostgreSQL path.
     */
    public function lower(BoundStatement $operation, ParsedDrop $statement): DropIndex
    {
        $context = $operation->context;
        Check::input($statement->kind === ObjectKind::Index && $context->profile->parameters === ParameterStyle::Native
            && $context->relationNames === Comparison::Sensitive, 'DROP INDEX requires the native PostgreSQL index profile.');
        $bindings = new IndexTargetLowering();
        $targets = [];
        foreach ($statement->objects as $selector) {
            Check::input($selector instanceof DottedName && count($selector->parts) <= 2, 'DROP INDEX names a local index and optional schema.');
            $name = $selector->qualified();
            Check::input($name !== null, 'DROP INDEX needs a valid index name.');
            $targets[] = $bindings->resolve($context, $name, $statement->ifExists);
        }

        return new DropIndex(
            $targets,
            $statement->ifExists,
            $statement->behavior === DropBehavior::Cascade,
            $statement->concurrently ? IndexDropMode::Concurrently : IndexDropMode::Ordinary,
            $context->profile->grammar,
        );
    }

}
