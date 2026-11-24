<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Facade\Index\IndexCreationVisibility;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\CreateIndex as ParsedCreateIndex;

/**
 * Publishes the checked PostgreSQL B-tree role.
 *
 * @visibility SqlSemantics
 */
final class CreateIndexLowering
{
    /**
     * Resolves owner, scope, presence and direct column operands.
     */
    public function lower(BoundStatement $operation, ParsedCreateIndex $statement): CreateIndex
    {
        $context = $operation->context;
        Check::input($context->profile->parameters === ParameterStyle::Native && $context->relationNames === Comparison::Sensitive
            && $context->columnNames === Comparison::Sensitive, 'CREATE INDEX requires the native PostgreSQL index profile.');
        Check::input(
            $statement->name !== null && ($statement->method === null || $statement->method->value === 'btree')
            && $statement->options === [] && $statement->tablespace === null && $statement->where === null,
            'CREATE INDEX needs a named ordinary B-tree without additional index options.'
        );
        $target = (new IndexTableLowering())->lower($operation, $statement);
        $schema = new Name($target->schema->value);
        $occupied = (new IndexCreationVisibility())->occupied($context, $context->indexNamespace->indexes, $statement->name, $schema);
        Check::input(
            $occupied || ($context->complete && !$context->indexNamespace->hasUnknownNames($schema)),
            'CREATE INDEX cannot infer name availability in an open index namespace.'
        );
        $operands = new IndexOperands();
        $keys = [];
        $included = [];
        foreach ($statement->elements as $element) {
            $keys[] = $operands->key($target, $element);
        }
        foreach ($statement->included as $element) {
            $included[] = $operands->covering($target, $element);
        }

        return new CreateIndex(
            $target,
            new Identifier($statement->name->value),
            $keys,
            $included,
            $occupied ? IndexPresence::Occupied : IndexPresence::Vacant,
            $statement->ifNotExists,
            $statement->unique,
            $statement->nullsDistinct === false,
            $statement->concurrently,
            $context->profile->grammar,
        );
    }
}
