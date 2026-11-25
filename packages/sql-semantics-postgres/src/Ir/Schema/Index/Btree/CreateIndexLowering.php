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
    public function lower(
        BoundStatement $operation,
        ParsedCreateIndex $statement,
        ?Identifier $memberNamespace = null,
        ?IndexPresence $memberPresence = null,
    ): CreateIndex {
        $context = $operation->context;
        Check::input($context->profile->parameters === ParameterStyle::Native && $context->relationNames === Comparison::Sensitive
            && $context->columnNames === Comparison::Sensitive, 'CREATE INDEX requires the native PostgreSQL index profile.');
        Check::input(
            $statement->name !== null && ($statement->method === null || $statement->method->value === 'btree')
            && $statement->options === [] && $statement->tablespace === null,
            'CREATE INDEX needs a named ordinary B-tree without additional index options.'
        );
        Check::input(($memberNamespace === null) === ($memberPresence === null), 'A schema index requires its namespace and name occupancy together.');
        $target = (new IndexTableLowering())->lower($operation, $statement, $memberNamespace);
        $presence = $memberPresence;
        if ($presence === null) {
            $schema = new Name($target->target->schema->value);
            $occupied = (new IndexCreationVisibility())->occupied($context, $context->indexNamespace->indexes, $statement->name, $schema);
            Check::input(
                $occupied || ($context->complete && !$context->indexNamespace->hasUnknownNames($schema)),
                'CREATE INDEX cannot infer name availability in an open index namespace.'
            );
            $presence = $occupied ? IndexPresence::Occupied : IndexPresence::Vacant;
        }
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
            $presence,
            $statement->ifNotExists,
            $statement->unique,
            $statement->nullsDistinct === false,
            $statement->concurrently,
            $context->profile->grammar,
            (new IndexPredicateLowering())->lower($operation, $statement),
        );
    }
}
