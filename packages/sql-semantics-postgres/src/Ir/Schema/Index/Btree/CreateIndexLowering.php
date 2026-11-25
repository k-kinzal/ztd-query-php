<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CreateTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\Binding\IndexNameLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
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
     * @param array<array-key, CreateTable|TableName|true>|null $memberOccupants
     */
    public function lower(
        BoundStatement $operation,
        ParsedCreateIndex $statement,
        ?Identifier $memberNamespace = null,
        ?array $memberOccupants = null,
    ): CreateIndex {
        $context = $operation->context;
        Check::input($context->profile->parameters === ParameterStyle::Native && $context->relationNames === Comparison::Sensitive
            && $context->columnNames === Comparison::Sensitive, 'CREATE INDEX requires the native PostgreSQL index profile.');
        Check::input(
            ($statement->name !== null || !$statement->ifNotExists) && ($statement->method === null || $statement->method->value === 'btree')
            && $statement->options === [] && $statement->tablespace === null,
            'CREATE INDEX needs an ordinary B-tree without additional index options.'
        );
        Check::input(($memberNamespace === null) === ($memberOccupants === null), 'A schema index requires its namespace and known occupants together.');
        $target = (new IndexTableLowering())->lower($operation, $statement, $memberNamespace);
        $operands = new IndexOperands();
        $keys = [];
        $included = [];
        foreach ($statement->elements as $element) {
            $keys[] = $operands->key($target, $element);
        }
        foreach ($statement->included as $element) {
            $included[] = $operands->covering($target, $element);
        }

        [$name, $presence] = (new IndexNameLowering())->lower($context, $target, $statement->name, $keys, $included, $memberOccupants);

        return new CreateIndex(
            $target,
            $name,
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
