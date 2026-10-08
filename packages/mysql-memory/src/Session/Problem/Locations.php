<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem;

use MySqlMemory\Error\DataError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Locator;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\DefaultOfColumn;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\FullTextSearch;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;

/**
 * Finds the problems the server reports where it resolves a name, and the clause and resolution order each is found at.
 *
 * A name or a select list position that does not resolve, a call of a function the server does
 * not find, a clock call whose precision is above 6, a cast to an array outside a functional
 * index, DEFAULT() of a column without a default, and IN, ANY or ALL over a subquery of the wrong
 * width are each found where the server resolves them (Locator); the first of them is the one the
 * server reports (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html.
 *
 * @visibility MySqlMemory
 */
final class Locations
{
    /**
     * Answers the problems found where names are resolved, by object id, each with its clause and resolution order; and the problems of a column of MATCH, by object id.
     *
     * @param list<FunctionCall> $calls The calls of functions the server does not find
     * @param list<Diagnostic> $diagnostics The problems of the statement the server reports
     * @return array{array<int, array{Diagnostic|FunctionCall|ClockCall|SqlError, array{string, list<int>}}>, array<int, true>}
     */
    public function located(Operation $operation, array $calls, array $diagnostics, Session $session): array
    {
        $locator = (new Locator())->statement($operation->statement);
        [$located, $matched] = $this->names($operation, $locator, $calls, $diagnostics);
        foreach ($locator->arrays as [$cast, $clause, $at]) {
            $refusal = new NotSupportedYet(Cast::ARRAY_OUTSIDE_INDEX);
            $located[spl_object_id($refusal)] = [$refusal, [$clause, $at]];
        }
        $located = $this->defaults($operation, $locator, $session, $located);

        return [$this->widths($operation, $locator, $diagnostics, $located), $matched];
    }

    /**
     * Answers the problems of the located names, positions, calls and clock calls, by object id, each with its place; and those of a column of MATCH, by object id.
     *
     * A diagnostic the statement does not report, and a call the server finds, is left out.
     *
     * @param list<FunctionCall> $calls The calls of functions the server does not find
     * @param list<Diagnostic> $diagnostics The problems of the statement the server reports
     * @return array{array<int, array{Diagnostic|FunctionCall|ClockCall|SqlError, array{string, list<int>}}>, array<int, true>}
     */
    public function names(Operation $operation, Locator $locator, array $calls, array $diagnostics): array
    {
        $dropped = array_diff(array_map(spl_object_id(...), $operation->facts->diagnostics), array_map(spl_object_id(...), $diagnostics));
        $searched = [];
        foreach ((new Walker())->find($operation->statement, FullTextSearch::class) as $search) {
            foreach ($search->columns as $column) {
                $searched[spl_object_id($column)] = true;
            }
        }
        $located = [];
        $matched = [];
        foreach ([...$locator->nodes(), ...$locator->clocks()] as $node) {
            $problem = $operation->facts->covers($node) ? $this->problem($node, $operation) : null;
            $problem = $problem instanceof FunctionCall && !in_array($problem, $calls, true) ? null : $problem;
            $problem = $problem instanceof Diagnostic && !in_array($problem, $operation->facts->diagnostics, true) ? null : $problem;
            $place = $locator->place($node);
            if ($problem !== null && $place !== null && !in_array(spl_object_id($problem), $dropped, true)) {
                $located[spl_object_id($problem)] = [$problem, $place];
                if (isset($searched[spl_object_id($node)])) {
                    $matched[spl_object_id($problem)] = true;
                }
            }
        }

        return [$located, $matched];
    }

    /**
     * Adds the refusals of DEFAULT() of a column without a default in a query, each placed right after the column it reads.
     *
     * @param array<int, array{Diagnostic|FunctionCall|ClockCall|SqlError, array{string, list<int>}}> $located
     * @return array<int, array{Diagnostic|FunctionCall|ClockCall|SqlError, array{string, list<int>}}>
     */
    public function defaults(Operation $operation, Locator $locator, Session $session, array $located): array
    {
        foreach ($operation->statement instanceof Query ? (new Walker())->find($operation->statement, DefaultOfColumn::class) : [] as $default) {
            $place = $locator->place($default->column);
            $name = $place === null ? null : $this->undefaulted($default, $operation, $session);
            if ($place !== null && $name !== null) {
                $refusal = DataError::NoDefaultForField->error($name);
                $located[spl_object_id($refusal)] = [$refusal, [$place[0], [...$place[1], 0]]];
            }
        }

        return $located;
    }

    /**
     * Adds the problems of IN, ANY and ALL over a subquery of the wrong width, each placed before or after its operand; a problem of the statement answers one predicate only.
     *
     * @param list<Diagnostic> $diagnostics The problems of the statement the server reports
     * @param array<int, array{Diagnostic|FunctionCall|ClockCall|SqlError, array{string, list<int>}}> $located
     * @return array<int, array{Diagnostic|FunctionCall|ClockCall|SqlError, array{string, list<int>}}>
     */
    public function widths(Operation $operation, Locator $locator, array $diagnostics, array $located): array
    {
        $claimed = [];
        foreach ($locator->predicates as [$predicate, $clause, $at]) {
            $width = $this->width($predicate, $operation, $diagnostics, $claimed);
            if ($width !== null) {
                $claimed[] = $width[0];
                $located[spl_object_id($width[0])] = [$width[0], [$clause, [...$at, $width[1] ? 1 : 2]]];
            }
        }

        return $located;
    }

    /**
     * Answers the located problem the server resolves first, or null when there is none; of two at the same order, the one located first.
     *
     * @param array<int, array{Diagnostic|FunctionCall|ClockCall|SqlError, array{string, list<int>}}> $located
     * @return array{Diagnostic|FunctionCall|ClockCall|SqlError, array{string, list<int>}}|null
     */
    public static function first(array $located): ?array
    {
        $first = null;
        foreach ($located as $candidate) {
            if ($first === null || Locator::precedes($candidate[1][1], $first[1][1])) {
                $first = $candidate;
            }
        }

        return $first;
    }

    /**
     * Tells whether a call names a function that is neither native nor declared: a stored function the server does not find.
     */
    public static function undeclared(FunctionCall $call, Operation $operation): bool
    {
        if (!$operation->facts->covers($call)) {
            return false;
        }
        $type = $operation->facts->scalar($call)->type;
        if (!$type instanceof Dependent) {
            return false;
        }
        foreach ($type->missing as $missing) {
            if ($missing instanceof UndeclaredRoutine && $missing->name->name === $call->name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the problem of a located node: the diagnostic a name or an ordinal resolves to, a call of a function the server does not find, or a clock call whose precision is above 6.
     */
    public function problem(ColumnUse|OutputOrdinal|FunctionCall|ClockCall $node, Operation $operation): Diagnostic|FunctionCall|ClockCall|null
    {
        if ($node instanceof FunctionCall) {
            return self::undeclared($node, $operation) ? $node : null;
        }
        if ($node instanceof ClockCall) {
            return $node->decimals() > 6 ? $node : null;
        }
        $fact = $operation->facts->scalar($node);
        if ($fact->resolution instanceof Diagnostic) {
            return $fact->resolution;
        }

        return $node instanceof OutputOrdinal && $fact->type instanceof Invalid ? $fact->type->cause : null;
    }

    /**
     * Answers the name of the column DEFAULT() reads when the column is of a stored table and has no default, else null.
     *
     * The server refuses such a DEFAULT() where it resolves it (ER_NO_DEFAULT_FOR_FIELD), whether
     * any row is read or not (verified on a live 8.4 server).
     */
    public function undefaulted(DefaultOfColumn $default, Operation $operation, Session $session): ?string
    {
        $resolution = $operation->facts->covers($default->column) ? $operation->facts->scalar($default->column)->resolution : null;
        $declaration = $resolution instanceof ResolvedColumn ? $resolution->slot->declaration() : null;
        if ($declaration === null) {
            return null;
        }
        foreach ($session->instance->dictionary->schemas as $schema) {
            foreach ($schema->tables as $table) {
                foreach ($table->definition->columns as $column) {
                    if ($column->declaration === $declaration) {
                        return $column->default->declared ? null : $column->name;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Answers the problem of IN, ANY or ALL over a subquery whose width is not the width of its single-valued operand, and whether the server finds it before it resolves the operand.
     *
     * The server checks the width after it resolves the subquery: before it resolves the operand
     * of ALL, and of ANY with an operator other than `=` (Locator::early()), and after it for IN,
     * `= ANY` and `<> ALL` (verified on a live 8.4 server). The problem is the one the statement
     * reports, or a new one when the operand did not resolve.
     *
     * @param list<Diagnostic> $diagnostics The problems of the statement
     * @param list<Diagnostic> $claimed The problems already answered for another predicate
     * @return array{OperandColumns, bool}|null
     */
    public function width(InQuery|QuantifiedComparison $predicate, Operation $operation, array $diagnostics, array $claimed): ?array
    {
        $operand = $predicate->operand;
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }
        if ($operand instanceof Row || !$operation->facts->covers($predicate->query)) {
            return null;
        }
        $shape = $operation->facts->query($predicate->query)->shape;
        $width = count($shape->slots);
        if (!$shape->complete() || $width === 1) {
            return null;
        }
        $early = Locator::early($predicate);
        foreach ($diagnostics as $diagnostic) {
            if ($diagnostic instanceof OperandColumns && $diagnostic->expected === 1 && $diagnostic->actual === $width && !in_array($diagnostic, $claimed, true)) {
                return [$diagnostic, $early];
            }
        }

        return $early ? [new OperandColumns(1, $width), true] : null;
    }
}
