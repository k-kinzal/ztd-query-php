<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Insert;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;

/**
 * An unused VALUES-write ordering that cannot be an ordinary scalar ordering.
 *
 * Its expressions and directions remain typed values for rendering and facts.
 * Statement constructors admit this role only around a standalone VALUES write.
 *
 * @visibility public
 * @example Retaining a non-scalar ordering without manufacturing a Scalar
 *     $rows = new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')])]);
 *     $call = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DormantCall(new \SqlSemantics\Statement\Identifier\Name('ABS'));
 *     $source = new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesClauses(null, $rows, [new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem($call)]);
 *     $source->body === $rows // => true
 */
final class ValuesClauses implements Query
{
    use \SqlSemantics\Statement\Semantics\QuerySemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var non-empty-list<OrderItem|DeferredOrderItem> Written ordering items
     */
    public readonly array $orderBy;

    /**
     * @param WithClause|null $with The unused common-table definitions
     * @param Query $body The source enclosed by the clauses
     * @param non-empty-list<OrderItem|DeferredOrderItem> $orderBy The unused ordering
     * @param Limit|null $limit The unused row limit
     */
    public function __construct(public readonly ?WithClause $with, public readonly Query $body, array $orderBy, public readonly ?Limit $limit = null, ?QueryFact $queryInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($queryInformation !== null) {
            $this->initializeQueryInformation($queryInformation);
        }
        $items = [];
        foreach (Check::listOf($orderBy, Node::class, 'A retained ordering has items.', 1) as $item) {
            Check::input($item instanceof OrderItem || $item instanceof DeferredOrderItem, 'An ordering retains scalar or deferred item roles.');
            $items[] = $item;
        }
        $this->orderBy = $items;
        Check::input(array_filter($items, static fn ($item): bool => $item instanceof DeferredOrderItem) !== [], 'An unused ordering retains a non-scalar item.');
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Dml\ValueSources())->values($body) !== null, 'Unused ordering clauses enclose a standalone VALUES source.');
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReachedTables())->problem($with, [$body]) === null, 'A common table expression the statement reads carries no HIGH_PRIORITY, SQL_BUFFER_RESULT, SQL_CALC_FOUND_ROWS or SQL_CACHE, and no block with ALL and DISTINCT.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives every retained operand while withholding unused contextualization conditions.
     */
    public function deriveQuery(Derivation $derivation, Environment $outer): QueryFact
    {
        (new \SqlSemantics\Platform\MySql\Rules\Dml\ValueSources())->deferred($this, $derivation, fn () => $this->with?->bind($derivation, $outer));
        $fact = $derivation->query($this->body, $outer);
        (new \SqlSemantics\Platform\MySql\Rules\Dml\ValueSources())->deferred($this, $derivation, function () use ($derivation, $outer, $fact): void {
            (new \SqlSemantics\Platform\MySql\Rules\Routine\Selection\SelectionFacts())->ordering($this->orderBy, $derivation, $outer, $fact->projection, true);
            (new \SqlSemantics\Platform\MySql\Rules\Query\Tail\TailFacts())->limit($this->limit, $derivation, $outer);
        });
        return $fact;
    }

    /**
     * Writes every unused clause without reconstructing source text.
     */
    public function render(Output $out): void
    {
        $out->node($this->with)->node($this->body)->keyword('ORDER', 'BY')->list($this->orderBy)->node($this->limit);
    }
}
