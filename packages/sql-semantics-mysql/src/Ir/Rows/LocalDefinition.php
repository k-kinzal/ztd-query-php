<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Rows;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;

/**
 * Lowers the independent definition and exact result positions of a local read.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LocalDefinition
{
    /**
     * Checks an independent derived query before lowering.
     *
     * @throws InvalidConstruction When the local source is outside the independent domain
     */
    public function resolve(DerivedTable $source): ParsedSelect|QueryExpression
    {
        Check::input(!$source->lateral, 'The local definition cannot depend on a lateral scope.');
        $query = $source->query;
        while ($query instanceof ParenthesizedQuery) {
            $query = $query->query;
        }
        Check::input($query instanceof ParsedSelect || ($query instanceof QueryExpression && $query->with !== null), 'A local definition requires an independent SELECT.');

        return $query;
    }

    /**
     * Checks a projection consumer before resolving its local input.
     *
     * @throws InvalidConstruction When the root or an outer clause has no local IR representation
     */
    public function consumer(ParsedSelect|QueryExpression $statement): ParsedSelect
    {
        $body = $this->queryBlock($statement);
        Check::input($body->where === null, 'The local projection requires an ordinary result without outer clauses.');

        return $body;
    }

    /**
     * Resolves result fields to exact positions of this local input.
     * @return list<LocalResultColumn> Result positions and their observed labels
     */
    public function columns(BoundStatement $operation, ParsedSelect $query, DerivedTable|TableReference $source): array
    {
        $inputs = $operation->facts->relation($source)->shape->slots;
        $fields = $operation->facts->query($query)->fields();
        Check::input($fields !== null, 'The local projection must resolve all output positions.');
        $results = [];
        foreach ($query->items as $position => $item) {
            Check::input($item instanceof SelectExpression, 'A local projection requires explicit output columns.');
            $resolution = $operation->facts->scalar($item->expression)->resolution;
            Check::input(
                $resolution instanceof ResolvedColumn && $resolution->relation === $source && $resolution->depth === 0,
                'Each local result must read a resolved local slot.'
            );
            $slot = array_search($resolution->slot, $inputs, true);
            $merged = $operation->context->profile->grammar !== GrammarRelease::MySql5651;
            $name = $merged && $item->alias === null && $resolution->slot->origin?->origin !== null
                ? $resolution->slot->name
                : $fields->at($position)->name;
            Check::input($slot !== false && $name !== null, 'A local result needs a named, resolved slot.');
            $results[] = new LocalResultColumn($slot, new Identifier(ltrim($name->value, " \t\r\n\v\f")));
        }

        return $results;
    }

    /**
     * Checks the clauses shared by a local consumer and a recursive step.
     */
    public function queryBlock(ParsedSelect|QueryExpression $statement): ParsedSelect
    {
        $body = $statement instanceof QueryExpression ? $statement->body : $statement;
        Check::input($body instanceof ParsedSelect, 'A local projection requires a query with a local source.');
        Check::input(
            $body->groupBy === null && $body->having === null && $body->windows === []
            && $body->qualify === null && $body->orderBy === [] && $body->limit === null && $body->procedure === null
            && $body->locking === [] && $body->into === null && $body->late === null && ($body->options === [] || $body->options === [SelectOption::All]),
            'The local projection requires an ordinary result without outer clauses.'
        );

        return $body;
    }
}
