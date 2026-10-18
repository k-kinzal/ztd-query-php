<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine\Selection;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Query\From\FromScope;
use SqlSemantics\Platform\MySql\Rules\Query\From\JoinedInput;
use SqlSemantics\Platform\MySql\Rules\Query\Projection;
use SqlSemantics\Platform\MySql\Rules\Query\SortScopes;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Statement\Shape\RowShape;

/**
 * Binds a stored selection's complete clauses while retaining preparation conditions.
 *
 * The ordinary projections establish aliases before grouping, HAVING and ordering.
 * A deferred projection retains an invalid output slot and derives its operand
 * without manufacturing a scalar expression.
 * The program instruction retains its original operand and clause roles.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SelectionFacts
{
    /**
     * Derives every retained clause in its input or output-name scope.
     *
     * @return list<Field|OpenStar> Output positions, retaining invalid types for deferred projections
     */
    public function derive(DeferredSelection $selection, Derivation $derivation, ProgramScope $scope): array
    {
        (new \SqlSemantics\Platform\MySql\Rules\Query\SelectOptions())->raise($selection, $derivation);
        $from = $selection->from === null ? new JoinedInput(new RelationFact(new RowShape([])), [], []) : (new FromScope())->open($selection->from, $derivation, $scope->environment, []);
        (new FromScope())->unique($from->visible, $derivation);
        $input = new Environment($derivation->context, $scope->environment, $from->visible);
        $expressions = new Environment($derivation->context, $scope->environment, $from->visible, aggregatesAllowed: true);
        $fields = $this->projection($selection, $derivation, $expressions, $from);
        $aliases = (new \SqlSemantics\Platform\MySql\Rules\Query\SelectFacts())->aliases($selection, $fields, $derivation->context->profile);
        if ($selection->where !== null) {
            $derivation->scalar($selection->where, $input);
        }
        $groups = new Environment($derivation->context, $scope->environment, $from->visible, aliases: $aliases);
        $results = new Environment($derivation->context, $scope->environment, $from->visible, aliases: $aliases, aggregatesAllowed: true);
        $this->ordering($selection->groupBy->items ?? [], $derivation, $groups, $fields, false);
        if ($selection->having !== null) {
            $derivation->scalar($selection->having, $results);
        }
        (new \SqlSemantics\Platform\MySql\Rules\Query\SelectFacts())->windows($selection, $derivation, $expressions);
        (new \SqlSemantics\Platform\MySql\Rules\Query\WindowReferences())->check($selection, $derivation);
        $this->ordering($selection->orderBy, $derivation, $results, $fields, true);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Tail\TailFacts())->limit($selection->limit, $derivation, $scope->environment);
        if ($selection->procedure !== null) {
            \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation::raise(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::ProcedureAnalyse, $derivation);
            foreach ($selection->procedure->arguments as $argument) {
                $derivation->scalar($argument, $scope->environment);
            }
        }
        return $fields;
    }

    /**
     * Binds projections without manufacturing a scalar for a deferred operand.
     *
     * @return list<Field|OpenStar> The ordinary projected fields
     */
    public function projection(DeferredSelection $selection, Derivation $derivation, Environment $environment, JoinedInput $from): array
    {
        $ordinary = array_filter($selection->items, static fn ($item): bool => $item instanceof DeferredProjection) === [];
        $fields = [];
        $items = [];
        foreach ($selection->items as $item) {
            if ($item instanceof DeferredProjection) {
                $fact = $derivation->scalar($item->expression, $environment);
                $problem = (new \SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments())->problem([$item->expression]);
                $fields[] = new Field(count($fields), new \SqlSemantics\Statement\Shape\OutputSlot($item->alias, new \SqlSemantics\Statement\Type\Invalid($problem), $fact->nullability));
            } elseif ($ordinary) {
                $items[] = $item;
            } else {
                foreach ((new Projection())->items([$item], $derivation, $environment, $from) as $field) {
                    $fields[] = $field instanceof Field ? new Field(count($fields), $field->slot, $field->expression, $field->resolution) : $field;
                }
            }
        }
        return $ordinary ? (new Projection())->items($items, $derivation, $environment, $from) : $fields;
    }

    /**
     * Retains ordinal and alias resolution while deriving deferred item operands.
     *
     * @param list<\SqlSemantics\Platform\MySql\Statement\Query\OrderItem|DeferredOrderItem> $items
     * @param list<Field|OpenStar> $fields The ordinary output fields
     */
    public function ordering(array $items, Derivation $derivation, Environment $environment, array $fields, bool $aliasFirst): void
    {
        foreach ($items as $item) {
            if ($item instanceof DeferredOrderItem) {
                $derivation->scalar($item->expression, $environment);
            } else {
                (new SortScopes())->derive([$item], $derivation, $environment, $fields, $aliasFirst);
            }
        }
    }
}
