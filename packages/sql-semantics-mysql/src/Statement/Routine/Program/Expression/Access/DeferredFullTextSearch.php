<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Access;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\FullTextMode;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A full-text search retaining a non-scalar search value for deferred preparation.
 *
 * The column references, modes and written form remain binding dependencies.
 * This value is not an ordinary Scalar.
 *
 * @visibility public
 * @example Retaining the preparation role
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("WITH x AS (SELECT MATCH(a) AGAINST(ROW(1,2))) SELECT 1");
 *     $query->toString() // => "WITH x AS (SELECT MATCH (a) AGAINST (ROW(1, 2))) SELECT 1"
 */
final class DeferredFullTextSearch implements DeferredOperand
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var non-empty-list<ColumnUse> The searched columns in order
     */
    public readonly array $columns;

    /**
     * @param list<ColumnUse> $columns The searched columns in order; at least one
     * @param RowOperand|DeferredOperand $against The search string
     * @param FullTextMode $mode The search mode
     * @param OptionalWords $parentheses Whether the column list is written in parentheses
     * @param OptionalWords $stated Whether a natural language search writes IN NATURAL LANGUAGE MODE
     */
    public function __construct(array $columns, public readonly RowOperand|DeferredOperand $against, public readonly FullTextMode $mode = FullTextMode::NaturalLanguage, public readonly OptionalWords $parentheses = OptionalWords::Written, public readonly OptionalWords $stated = OptionalWords::Omitted, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        $this->columns = Check::listOf($columns, ColumnUse::class, 'MATCH searches at least one column.', 1);
        $precedence = new Precedence();
        Check::input($precedence->admits($against, Precedence::BIT_EXPR), 'The search string of AGAINST needs a grouping to keep its place.');
        Check::input($stated === OptionalWords::Omitted || $mode !== FullTextMode::Boolean, 'Only a natural language search states IN NATURAL LANGUAGE MODE.');
        Check::input(($mode !== FullTextMode::Boolean && $stated === OptionalWords::Omitted) || !$precedence->absorbs($against, Precedence::PREDICATE, Precedence::BIT_EXPR), 'A search string followed by IN ... MODE needs a grouping to keep its place.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives column dependencies and retains the search-value preparation condition.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operands = new Operands();
        foreach ($this->columns as $column) {
            $operands->single($derivation->scalar($column, $environment), $derivation);
        }
        $derivation->scalar($this->against, $environment);

        return new ScalarFact(new \SqlSemantics\Statement\Type\Invalid($this->problem()), Nullability::Nullable);
    }

    /**
     * @return non-empty-list<Scalar|RowOperand|DeferredOperand> Columns followed by the search value
     */
    public function operands(): array
    {
        return [...$this->columns, $this->against];
    }

    /**
     * Describes the search value's retained preparation condition.
     */
    public function problem(): \SqlSemantics\Statement\Fact\Diagnostic
    {
        return (new \SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments())->problem([$this->against]);
    }

    /**
     * Writes MATCH, the columns, AGAINST, the search string and the mode.
     */
    public function render(Output $out): void
    {
        $out->keyword('MATCH');
        if ($this->parentheses === OptionalWords::Written) {
            $out->symbol('(')->list($this->columns)->symbol(')');
        } else {
            $out->list($this->columns);
        }
        $out->keyword('AGAINST')->symbol('(')->node($this->against);
        if ($this->stated === OptionalWords::Written) {
            $out->keyword('IN', 'NATURAL', 'LANGUAGE', 'MODE');
        }
        match ($this->mode) {
            FullTextMode::NaturalLanguage => $out,
            FullTextMode::QueryExpansion => $out->keyword('WITH', 'QUERY', 'EXPANSION'),
            FullTextMode::Boolean => $out->keyword('IN', 'BOOLEAN', 'MODE'),
        };
        $out->symbol(')');
    }
}
