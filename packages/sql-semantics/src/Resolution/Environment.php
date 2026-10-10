<?php

declare(strict_types=1);

namespace SqlSemantics\Resolution;

use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\Field;

/**
 * What names mean at one position of a statement.
 *
 * An environment is derived from the clause position and the relation
 * structure: the occurrences visible there, the enclosing query positions, the
 * common tables in scope and the output aliases the position may use. It is a
 * working value of one derivation and is not part of the published model.
 *
 * @visibility SqlSemantics
 */
final class Environment
{
    /**
     * @param AnalysisContext $context The declaration context
     * @param Environment|null $outer The position of the enclosing query that lookups continue in
     * @param list<VisibleRelation> $relations The relation occurrences visible at this position
     * @param list<CommonBinding> $commonTables The common tables introduced at this level; later bindings shadow earlier ones
     * @param list<Field> $aliases The output fields this position may refer to by alias
     * @param bool $written Whether the position names the columns a statement writes, where a name is a column and never a variable of a stored program
     * @param AggregationScope|null $aggregation The query block collecting aggregate occurrences
     * @param bool $aggregatesAllowed Whether this clause can own an aggregate
     */
    public function __construct(
        public readonly AnalysisContext $context,
        public readonly ?Environment $outer = null,
        public readonly array $relations = [],
        public readonly array $commonTables = [],
        public readonly array $aliases = [],
        public readonly bool $written = false,
        public readonly ?AggregationScope $aggregation = null,
        public readonly bool $aggregatesAllowed = false,
    ) {
    }

    /**
     * Finds the nearest common table with a name.
     */
    public function commonTable(Name $name): ?CommonBinding
    {
        for ($scope = $this; $scope !== null; $scope = $scope->outer) {
            for ($index = count($scope->commonTables) - 1; $index >= 0; $index--) {
                if ($this->context->relationNames->equal($scope->commonTables[$index]->name->value, $name->value)) {
                    return $scope->commonTables[$index];
                }
            }
        }

        return null;
    }

    /**
     * Finds the output fields of this position an alias names.
     *
     * @return list<Field>
     */
    public function aliased(Name $name): array
    {
        $fields = [];
        foreach ($this->aliases as $field) {
            if ($field->name !== null && $this->context->columnNames->equal($field->name->value, $name->value)) {
                $fields[] = $field;
            }
        }

        return $fields;
    }
}
