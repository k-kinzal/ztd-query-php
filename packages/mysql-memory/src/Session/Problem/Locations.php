<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Plan\Window\Resolution;
use MySqlMemory\Plan\Window\Windowing;
use MySqlMemory\Session\Locator;
use MySqlMemory\Session\Resolution\QueryPreparation;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
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
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountedList;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UnknownQualifier;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Scalar;
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
     * @return array{array<int, array{Diagnostic|FunctionCall|SqlError, array{string, list<int>}}>, array<int, true>}
     */
    public function located(MySqlStatement|QueryPreparation $operation, array $calls, array $diagnostics, Session $session, bool $prepared = false): array
    {
        $statement = $operation instanceof QueryPreparation ? $operation->statement : $operation;
        $locator = (new Locator($session->settings()->release() === GrammarRelease::MySql8044, $session->settings()->release(), $prepared))->statement($statement);
        [$located, $matched] = $this->names($operation, $locator, $calls, $diagnostics);
        $located = (new Location\Calls())->located($locator, $session, $located);
        foreach ($locator->arrays as [$cast, $clause, $at]) {
            $refusal = new NotSupportedYet(Cast::ARRAY_OUTSIDE_INDEX);
            $located[spl_object_id($refusal)] = [$refusal, [$clause, $at]];
        }
        $located = (new Location\Owned())->located($locator, $diagnostics, $located);
        $located = (new Location\WriteFunctions())->located($statement, $locator, $located);
        $located = (new Location\SetEncodings())->located($statement, $locator, $session, $located);
        $located = $this->defaults($operation, $locator, $session, $located);
        $located = $this->wildcards($locator, $diagnostics, $located);
        $located = $this->stars($operation, $locator, $diagnostics, $located);
        $located = $this->windows($locator, $diagnostics, $located);
        $located = $this->misplaced($locator, $located, ($statement) instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore);
        $located = $this->sets($operation, $locator, $diagnostics, $located);
        $common = (new IndexHints())->common($statement);
        foreach ($locator->hinted as [$reference, $order]) {
            $refusal = (new IndexHints())->refusal($reference, $common, $session);
            if ($refusal !== null) {
                $located[spl_object_id($refusal)] = [$refusal, ['field list', $order]];
            }
        }

        return [$this->widths($operation, $locator, $diagnostics, $located), $matched];
    }

    /**
     * Answers the problems of the located names, positions, calls and clock calls, by object id, each with its place; and those of a column of MATCH, by object id.
     *
     * A diagnostic the statement does not report, and a call the server finds, is left out.
     *
     * @param list<FunctionCall> $calls The calls of functions the server does not find
     * @param list<Diagnostic> $diagnostics The problems of the statement the server reports
     * @return array{array<int, array{Diagnostic|FunctionCall|SqlError, array{string, list<int>}}>, array<int, true>}
     */
    public function names(MySqlStatement|QueryPreparation $operation, Locator $locator, array $calls, array $diagnostics): array
    {
        $information = $operation instanceof QueryPreparation ? $operation->facts : $operation->statementInformation();
        $statement = $operation instanceof QueryPreparation ? $operation->statement : $operation;
        $dropped = array_diff(array_map(spl_object_id(...), ($information)->diagnostics), array_map(spl_object_id(...), $diagnostics));
        $searched = [];
        foreach ([...(new Walker())->find($statement, FullTextSearch::class), ...(new Walker())->find($statement, \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Access\DeferredFullTextSearch::class)] as $search) {
            foreach ($search->columns as $column) {
                $searched[spl_object_id($column)] = true;
            }
        }
        $located = [];
        $matched = [];
        foreach ([...$locator->nodes(), ...$locator->clocks()] as $node) {
            $problem = $node->hasBinding() ? $this->problem($node, $operation) : null;
            $problem = $problem instanceof FunctionCall && !in_array($problem, $calls, true) ? null : $problem;
            $problem = $problem instanceof Diagnostic && !in_array($problem, ($information)->diagnostics, true) ? null : $problem;
            $place = $locator->place($node);
            if ($problem !== null && $place !== null && !in_array(spl_object_id($problem), $dropped, true)) {
                $previous = $located[spl_object_id($problem)] ?? null;
                if ($previous === null || Locator::precedes($place[1], $previous[1][1])) {
                    $located[spl_object_id($problem)] = [$problem, $place];
                }
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
     * @param array<int, array{Diagnostic|FunctionCall|SqlError, array{string, list<int>}}> $located
     * @return array<int, array{Diagnostic|FunctionCall|SqlError, array{string, list<int>}}>
     */
    public function defaults(MySqlStatement|QueryPreparation $operation, Locator $locator, Session $session, array $located): array
    {
        $statement = $operation instanceof QueryPreparation ? $operation->statement : $operation;
        foreach ($operation instanceof QueryPreparation || ($statement) instanceof \SqlSemantics\Platform\MySql\Statement\QueryRoot ? (new Walker())->find($statement, DefaultOfColumn::class) : [] as $default) {
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
     * Adds the problems of `t.*` items whose qualifier names no table of their block, each placed before the items of the block.
     *
     * The server expands the stars of a select list before it resolves its items (verified on
     * live 8.0, 8.4 and 9.1 servers).
     *
     * @param list<Diagnostic> $diagnostics The problems of the statement the server reports
     * @param array<int, array{Diagnostic|FunctionCall|SqlError, array{string, list<int>}}> $located
     * @return array<int, array{Diagnostic|FunctionCall|SqlError, array{string, list<int>}}>
     */
    public function wildcards(Locator $locator, array $diagnostics, array $located): array
    {
        foreach ($locator->wildcards as [$wildcard, $at]) {
            foreach ($diagnostics as $diagnostic) {
                if ($diagnostic instanceof UnknownQualifier && $diagnostic->table === $wildcard->table) {
                    $located[spl_object_id($diagnostic)] = [$diagnostic, ['field list', $at]];
                }
            }
        }

        return $located;
    }

    /**
     * Adds the refusal of `*` in a block without tables, placed before the items of the block; EXISTS takes such a block.
     *
     * The server expands the star while it resolves the select list of the block, so the
     * subquery of IN, ANY and ALL, resolved before its operand, reports it first; the query of
     * EXISTS selects nothing and takes it (verified on live 5.6.51 and 8.4.7 servers).
     *
     * @param list<Diagnostic> $diagnostics The problems of the statement the server reports
     * @param array<int, array{Diagnostic|FunctionCall|SqlError, array{string, list<int>}}> $located
     * @return array<int, array{Diagnostic|FunctionCall|SqlError, array{string, list<int>}}>
     */
    public function stars(MySqlStatement|QueryPreparation $operation, Locator $locator, array $diagnostics, array $located): array
    {
        $misuses = array_values(array_filter($diagnostics, static fn (Diagnostic $diagnostic): bool => $diagnostic instanceof Misuse && $diagnostic->rule === MisuseRule::StarWithoutTables));
        if ($misuses === []) {
            return $located;
        }
        $existing = self::existing($operation);
        foreach ($locator->stars as [$select, $at]) {
            if (!in_array($select, $existing, true)) {
                $refusal = array_shift($misuses) ?? QueryError::NoTablesUsed->error();
                $located[spl_object_id($refusal)] = [$refusal, ['field list', $at]];
            }
        }

        return $located;
    }

    /**
     * Answers the query blocks EXISTS tests, through parentheses and set operations.
     *
     * @return list<\SqlSemantics\Platform\MySql\Statement\Query\Select>
     */
    public static function existing(MySqlStatement|QueryPreparation $operation): array
    {
        $statement = $operation instanceof QueryPreparation ? $operation->statement : $operation;
        $blocks = $operation instanceof QueryPreparation && $operation->exists ? (new \MySqlMemory\Session\Placement())->blocks($statement) : [];
        foreach ((new Walker())->find($statement, \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Exists::class) as $exists) {
            array_push($blocks, ...(new \MySqlMemory\Session\Placement())->blocks($exists->query));
        }

        return $blocks;
    }

    /**
     * Places window lookup and unsupported aggregate diagnostics within their query blocks.
     *
     * @param list<Diagnostic> $diagnostics The problems of the statement the server reports
     * @param array<int, array{Diagnostic|FunctionCall|SqlError, array{string, list<int>}}> $located
     * @return array<int, array{Diagnostic|FunctionCall|SqlError, array{string, list<int>}}>
     */
    public function windows(Locator $locator, array $diagnostics, array $located): array
    {
        return (new Location\Windows())->located($locator, $diagnostics, $located);
    }

    /**
     * Adds the refusals of window functions outside the select list and ORDER BY of their block, or in an argument of another window function or of an aggregate, each placed before its arguments.
     *
     * The server refuses such a call where it resolves it, with ER_WINDOW_INVALID_WINDOW_FUNC_USE
     * (verified on a live 8.4 server).
     *
     * @param array<int, array{Diagnostic|FunctionCall|SqlError, array{string, list<int>}}> $located
     * @param bool $ungrouped Whether the expression has no query block to supply groups or windows
     * @return array<int, array{Diagnostic|FunctionCall|SqlError, array{string, list<int>}}>
     */
    public function misplaced(Locator $locator, array $located, bool $ungrouped = false): array
    {
        $walker = new Walker();
        $nested = [];
        foreach ($locator->functions as [$call]) {
            $window = $call->over instanceof WindowSpec ? array_map(spl_object_id(...), $walker->find($call->over, Scalar::class, false)) : [];
            foreach ($walker->find($call, Scalar::class, false) as $node) {
                if ($node !== $call && Windowing::windowed($node) && !in_array(spl_object_id($node), $window, true)) {
                    $nested[spl_object_id($node)] = true;
                }
            }
        }
        foreach ($locator->functions as [$call, $clause, $at]) {
            if ($ungrouped) {
                $refusal = Windowing::windowed($call) ? Resolution::unavailable($call, new \MySqlMemory\Evaluation\Scope()) : QueryError::InvalidGroupFunctionUse->error();
                $located[spl_object_id($refusal)] = [$refusal, [$clause, $at]];
                continue;
            }
            if (Windowing::windowed($call) && (isset($nested[spl_object_id($call)]) || !in_array($clause, ['field list', 'order clause', 'window partition by', 'window order by'], true))) {
                $refusal = QueryError::WindowFunctionMisplaced->error(Resolution::named($call));
                $located[spl_object_id($refusal)] = [$refusal, [$clause, $at]];
            }
        }

        return $located;
    }

    /**
     * Adds the problems of set operations whose operands have different numbers of columns, each placed once its right operand is resolved; a problem of the statement answers one operation only.
     *
     * The server compares the columns of the operands of each set operation after it has
     * resolved them, before it resolves the operand that follows or the ORDER BY of the
     * operation (verified on a live 8.4 server).
     *
     * @param list<Diagnostic> $diagnostics The problems of the statement the server reports
     * @param array<int, array{Diagnostic|FunctionCall|SqlError, array{string, list<int>}}> $located
     * @return array<int, array{Diagnostic|FunctionCall|SqlError, array{string, list<int>}}>
     */
    public function sets(MySqlStatement|QueryPreparation $operation, Locator $locator, array $diagnostics, array $located): array
    {
        $claimed = [];
        foreach ($locator->sets as [$set, $at]) {
            $left = $set->left instanceof Query && $set->left->hasBinding() ? $set->left->queryInformation()->shape : null;
            $right = $set->right->hasBinding() ? $set->right->queryInformation()->shape : null;
            if ($left === null || $right === null || !$left->complete() || !$right->complete()) {
                continue;
            }
            foreach ($diagnostics as $diagnostic) {
                if ($diagnostic instanceof CountMismatch && $diagnostic->list === CountedList::SetOperands && $diagnostic->expected === count($left->slots) && $diagnostic->actual === count($right->slots) && !in_array($diagnostic, $claimed, true)) {
                    $claimed[] = $diagnostic;
                    $located[spl_object_id($diagnostic)] = [$diagnostic, ['field list', $at]];
                    break;
                }
            }
        }

        return $located;
    }

    /**
     * Adds the problems of IN, ANY and ALL over a subquery of the wrong width, each placed before or after its operand; a problem of the statement answers one predicate only.
     *
     * @param list<Diagnostic> $diagnostics The problems of the statement the server reports
     * @param array<int, array{Diagnostic|FunctionCall|SqlError, array{string, list<int>}}> $located
     * @return array<int, array{Diagnostic|FunctionCall|SqlError, array{string, list<int>}}>
     */
    public function widths(MySqlStatement|QueryPreparation $operation, Locator $locator, array $diagnostics, array $located): array
    {
        $claimed = [];
        foreach ($locator->predicates as [$predicate, $clause, $at]) {
            $width = $this->width($predicate, $operation, $diagnostics, $claimed);
            if ($width !== null) {
                $claimed[] = $width[0];
                $located[spl_object_id($width[0])] = [$width[0], [$clause, [...$at, $width[1] && !$locator->operandFirst ? 1 : 2]]];
            }
        }

        return $located;
    }

    /**
     * Answers the located problem the server resolves first, or null when there is none; of two at the same order, the one located first.
     *
     * @param array<int, array{Diagnostic|FunctionCall|SqlError, array{string, list<int>}}> $located
     * @return array{Diagnostic|FunctionCall|SqlError, array{string, list<int>}}|null
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
    public static function undeclared(FunctionCall $call, MySqlStatement|QueryPreparation|\MySqlMemory\Session\Resolution\IndexPreparation $operation): bool
    {
        if (!$call->hasBinding()) {
            return false;
        }
        $type = $call->scalarInformation()->type;
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
    public function problem(ColumnUse|OutputOrdinal|FunctionCall|ClockCall $node, MySqlStatement|QueryPreparation $operation): Diagnostic|FunctionCall|null
    {
        if ($node instanceof FunctionCall) {
            return self::undeclared($node, $operation) ? $node : null;
        }
        if ($node instanceof ClockCall) {
            return null;
        }
        $fact = $node->scalarInformation();
        if ($fact->resolution instanceof Diagnostic) {
            return $fact->resolution;
        }

        return $node instanceof OutputOrdinal && $fact->type instanceof Invalid ? $fact->type->cause : null;
    }

    /**
     * Answers the name of the column DEFAULT() reads when the column is of a stored table and has no default, else null.
     *
     * The server refuses such a DEFAULT() where it resolves it (ER_NO_DEFAULT_FOR_FIELD), whether
     * any row is read or not; an AUTO_INCREMENT column reads as 0 (verified on a live 8.4 server).
     */
    public function undefaulted(DefaultOfColumn $default, MySqlStatement|QueryPreparation $operation, Session $session): ?string
    {
        $resolution = $default->column->hasBinding() ? $default->column->scalarInformation()->resolution : null;
        $declaration = $resolution instanceof ResolvedColumn ? $resolution->slot->declaration() : null;
        if ($declaration === null) {
            return null;
        }
        foreach ($session->instance->dictionary->schemas as $schema) {
            foreach ($schema->tables as $table) {
                foreach ($table->definition->columns as $column) {
                    if ($column->declaration === $declaration) {
                        return $column->default->declared || $column->autoIncrement ? null : ($session->settings()->release() === GrammarRelease::MySql5651 && $resolution->relation instanceof \SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable ? $default->column->name->value : $column->name);
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
    public function width(InQuery|QuantifiedComparison $predicate, MySqlStatement|QueryPreparation $operation, array $diagnostics, array $claimed): ?array
    {
        $early = Locator::early($predicate, $operation->profile()->grammar);
        foreach ($diagnostics as $diagnostic) {
            if ($diagnostic instanceof OperandColumns && $diagnostic->expression === $predicate) {
                return [$diagnostic, $early];
            }
        }
        $operand = $predicate->operand;
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }
        if ($operand instanceof Row || !$predicate->query->hasBinding()) {
            return null;
        }
        $shape = $predicate->query->queryInformation()->shape;
        $width = count($shape->slots);
        if (!$shape->complete() || $width < 2) {
            return null;
        }
        foreach ($diagnostics as $diagnostic) {
            if ($diagnostic instanceof OperandColumns && $diagnostic->expression === null && $diagnostic->expected === 1 && $diagnostic->actual === $width && !in_array($diagnostic, $claimed, true)) {
                return [$diagnostic, $early];
            }
        }

        return $early ? [new OperandColumns(1, $width), true] : null;
    }
}
