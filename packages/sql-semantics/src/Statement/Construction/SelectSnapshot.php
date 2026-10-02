<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction;

use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Projection\Field;
use SqlSemantics\Statement\Projection\Fields;
use SqlSemantics\Statement\Query\Quantifier;
use SqlSemantics\Statement\Query\SqliteLimit;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\SqliteAliasScope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Validation\Check;

/**
 * A SELECT snapshot derived solely from explicit new inputs and one fixed environment.
 * @visibility SqlSemantics
 */
final class SelectSnapshot
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Fresh input occurrences and their declaration context.
     */
    public readonly Scope $scope;
    /**
     * The actual ordered output operands.
     */
    public readonly Fields $projection;
    /**
     * The predicate resolved after projection aliases become visible.
     */
    public readonly ?ScalarExpression $where;
    /**
     * The requested duplicate handling.
     */
    public readonly Quantifier $quantifier;
    /**
     * Row-count and offset requests in their independent scope.
     */
    public readonly ?SqliteLimit $limit;

    /**
     * Every occurrence, field, and bound expression is newly derived for this query.
     */
    public function __construct(Catalog|Scope|SqliteAliasScope $context, Query\SelectDefinition $input)
    {
        $catalog = $context instanceof Catalog ? $context : $context->catalog;
        Check::input($catalog->profile->grammar->database() === 'sqlite', 'This SELECT constructor requires the SQLite semantic profile.');
        $this->scope = new Scope($context, ...array_map(static fn (Query\NamedInput $item): TableReference => new TableReference($catalog, $item->name, $item->alias, $item->explicitAlias), $input->from->items));
        $expressions = new ExpressionConstruction();
        $this->projection = new Fields($this->scope, ...array_map(fn (Query\FieldDefinition $field): Field => new Field($expressions->derive($field->expression, $this->scope), $field->alias, $field->explicitAlias), $input->projection->fields));
        $aliases = array_values(array_filter($this->projection->items, static fn (Field $field): bool => $field->alias !== null));
        $this->where = $input->where === null ? null : $expressions->derive($input->where, new SqliteAliasScope($this->projection, ...$aliases));
        $this->quantifier = $input->quantifier;
        $limitScope = new Scope($catalog);
        $this->limit = $input->limit === null ? null : new SqliteLimit($limitScope, $expressions->derive($input->limit->count, $limitScope), $input->limit->offset === null ? null : $expressions->derive($input->limit->offset, $limitScope), $input->limit->commaSyntax);
        Check::input((new \SqlSemantics\Statement\SemanticGraph())->containsOnlyValues($this), 'A SELECT retains only closed immutable semantic values.');
    }
}
