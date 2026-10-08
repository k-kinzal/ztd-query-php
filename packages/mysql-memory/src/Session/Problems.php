<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\DropIndex;
use SqlSemantics\Platform\MySql\Statement\Alter\DropTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\RepeatedTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\TableExists;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\UnknownAlterChoice;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\UnknownColumn;
use SqlSemantics\Platform\MySql\Statement\Alter\RenameTable;
use SqlSemantics\Platform\MySql\Statement\Call\Clock;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\NamedArgument;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\ReservedFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerOpen;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\DuplicateColumn as DuplicateWrittenColumn;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\GeneratedColumnWrite;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\UnknownDeleteTable;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\ValueCountMismatch;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\CollationMismatch;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\IllegalCollationMix;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation;
use SqlSemantics\Platform\MySql\Statement\Name\AmbiguousAlias;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\GroupingModifier;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProgramVariable;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoVariables;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountedList;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\GroupingRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\NonGroupedColumn;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\OrdinalOutOfRange;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UndeclaredVariable;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UnknownPartition;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UnknownQualifier;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UnpartitionedTable;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CacheIndex;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\LoadIndex;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\LockTables;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\AnalyzeTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\ChecksumTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\CheckTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\OptimizeTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\RepairTable;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\BucketCountOutOfRange;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\HistogramTables;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\UnknownHistogramColumn;
use SqlSemantics\Platform\MySql\Statement\Table\CreateIndex;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTableLike;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\DuplicateColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\IncorrectColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\MultiplePrimaryKeys;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\NoColumns;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\NullablePrimaryKey;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\UnknownKeyColumn;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\VariableMisuse;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\Warning;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Column\AmbiguousColumn;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;

/**
 * Raises the first problem SQL Semantics found in a statement as the error the server reports for it.
 *
 * The server reports the problems it finds while it reads the statement first, in the order it
 * reads them: a wrong call of a native function, arguments with aliases, a table alias used
 * twice, a LIMIT operand naming an undeclared variable, and a locking clause naming a table the
 * query lacks or locking a table twice. Then it checks the INTO variables. It then opens the
 * tables, refuses QUALIFY and a CUBE without tables, checks that each window a query names is
 * defined, resolves the names of each clause in order, which includes finding each stored
 * function a call names, and checks that no window is defined twice last.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/function-resolution.html,
 * https://dev.mysql.com/doc/refman/8.4/en/window-functions-named-windows.html.
 *
 * @visibility MySqlMemory
 */
final class Problems
{
    /**
     * Raises the error of the first problem of an operation, if any; an account statement raises its own, in the order its command checks them.
     *
     * @throws SqlError When the operation has a problem
     */
    public function raise(Operation $operation, Session $session): void
    {
        if (\MySqlMemory\Command\Account\Names::owns($operation->statement) || $operation->statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallComponent || $operation->statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushTables) {
            return;
        }
        if ((new \MySqlMemory\Command\Program\ProgramProblems())->raise($operation, $session, $this)) {
            return;
        }
        if ($operation->statement instanceof AlterTable || $operation->statement instanceof CreateIndex || $operation->statement instanceof DropIndex) {
            return;
        }
        $calls = array_values(array_filter((new Walker())->find($operation->statement, FunctionCall::class), static fn (FunctionCall $call): bool => self::undeclared($call, $operation)));
        if ($calls !== [] && (new Walker())->find($operation->statement, CommonTableExpression::class) !== []) {
            $reached = array_fill_keys(array_map(spl_object_id(...), $this->reached($operation->statement)), true);
            $calls = array_values(array_filter($calls, static fn (FunctionCall $call): bool => isset($reached[spl_object_id($call)])));
        }
        $grouping = $session->modes()->has('ONLY_FULL_GROUP_BY');
        $database = $session->variables->database;
        $diagnostics = array_values(array_filter($operation->facts->diagnostics, static fn (Diagnostic $diagnostic): bool => ($grouping || !$diagnostic instanceof NonGroupedColumn)
            && !self::answered($operation->statement, $diagnostic)
            && !($operation->statement instanceof DropTable && $diagnostic instanceof MissingTable && ($diagnostic->name->schema !== null || $database !== ''))));
        $this->read($operation, $session);
        foreach ((new Walker())->find($operation->statement, IntoVariables::class) as $into) {
            foreach ($into->targets as $target) {
                if ($target instanceof ProgramVariable) {
                    throw ErrorCode::UndeclaredVariable->error($target->name->value);
                }
            }
        }
        $this->prepared($operation, $session);
        foreach ($calls as $call) {
            if ($call->named()) {
                throw ErrorCode::WrongParametersToStoredFunction->error('`' . $call->name->value . '`');
            }
        }
        $path = (new JsonTables())->first($operation->statement);
        if ($path !== null) {
            foreach ($diagnostics as $diagnostic) {
                if ($diagnostic instanceof MissingTable || $diagnostic instanceof UnpartitionedTable || $diagnostic instanceof UnknownPartition) {
                    throw $this->error($diagnostic, $session, 'field list', $operation->statement);
                }
            }

            throw $path;
        }
        $locator = (new Locator())->statement($operation->statement);
        $dropped = array_diff(array_map(spl_object_id(...), $operation->facts->diagnostics), array_map(spl_object_id(...), $diagnostics));
        $located = [];
        foreach ([...$locator->nodes(), ...$locator->clocks()] as $node) {
            $problem = $operation->facts->covers($node) ? $this->problem($node, $operation) : null;
            $problem = $problem instanceof FunctionCall && !in_array($problem, $calls, true) ? null : $problem;
            $problem = $problem instanceof Diagnostic && !in_array($problem, $operation->facts->diagnostics, true) ? null : $problem;
            $place = $locator->place($node);
            if ($problem !== null && $place !== null && !in_array(spl_object_id($problem), $dropped, true)) {
                $located[spl_object_id($problem)] = [$problem, $place];
            }
        }
        foreach ($diagnostics as $diagnostic) {
            if (isset($located[spl_object_id($diagnostic)])) {
                break;
            }
            if (!self::late($diagnostic)) {
                throw $this->error($diagnostic, $session, 'field list', $operation->statement);
            }
        }
        foreach ($diagnostics as $diagnostic) {
            if ($diagnostic instanceof Misuse && $diagnostic->rule === MisuseRule::UnknownWindow) {
                throw $this->misuse($diagnostic);
            }
        }
        $first = null;
        foreach ($located as $candidate) {
            if ($first === null || Locator::precedes($candidate[1][1], $first[1][1])) {
                $first = $candidate;
            }
        }
        if ($first !== null) {
            throw match (true) {
                $first[0] instanceof FunctionCall => $this->routine($first[0], $session),
                $first[0] instanceof ClockCall => $this->precision($first[0]),
                default => $this->error($first[0], $session, $first[1][0], $operation->statement),
            };
        }
        foreach ($calls as $call) {
            throw $this->routine($call, $session);
        }
        foreach ($diagnostics as $diagnostic) {
            throw $this->error($diagnostic, $session, 'field list', $operation->statement);
        }
    }

    /**
     * Raises the error of the first problem the server finds while it reads an operation, before it checks the INTO variables and opens any table.
     *
     * A CAST or CONVERT to TIME or DATETIME with a precision above 6 is one of them. An unknown
     * collation after COLLATE comes first of all, as the server looks it up while it parses the
     * statement, and a table of a multiple-table DELETE that its FROM clause lacks comes after the
     * tables the FROM clause names twice, before any table is opened (verified on a live 8.4
     * server).
     *
     * @throws SqlError When the operation has such a problem
     */
    public function read(Operation $operation, Session $session): void
    {
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof UnknownCollation) {
                throw $this->error($diagnostic, $session, 'field list', $operation->statement);
            }
        }
        (new Placement())->check($operation->statement);
        $repeated = $this->repeated($operation->statement);
        if ($repeated !== null) {
            throw ErrorCode::NonUniqueTable->error($repeated->value);
        }
        $alias = null;
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if (\MySqlMemory\Command\Program\ProgramProblems::parameter($operation->statement, $diagnostic)) {
                continue;
            }
            if (self::parsed($diagnostic)) {
                throw $this->error($diagnostic, $session, 'field list', $operation->statement);
            }
            if (self::closing($diagnostic)) {
                throw $this->error($alias ?? $diagnostic, $session, 'field list', $operation->statement);
            }
            $alias ??= $diagnostic instanceof NonUniqueTable ? $diagnostic : null;
        }
        if ($alias !== null) {
            throw $this->error($alias, $session, 'field list', $operation->statement);
        }
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof UnknownDeleteTable) {
                throw $this->error($diagnostic, $session, 'field list', $operation->statement);
            }
        }
        foreach ((new Walker())->find($operation->statement, Cast::class) as $cast) {
            $target = $cast->target;
            if (($target->kind === CastKind::Time || $target->kind === CastKind::DateTime) && $target->length !== null && (int) $target->length > 6) {
                throw ErrorCode::TooBigPrecision->error((int) $target->length, 'CAST', 6);
            }
        }
        (new \MySqlMemory\Command\Show\Inspection())->check($operation->statement, $session);
        (new \MySqlMemory\Command\Explain\ExplainCommand())->check($operation->statement, $session);
    }

    /**
     * Raises the error of a clause the server refuses once it has opened the tables of the statement, before it resolves any name.
     *
     * GROUP BY CUBE in a statement that reads no table is not supported; with tables, CUBE fails
     * only when the statement runs (Grouping). QUALIFY needs the hypergraph optimizer, which the
     * server does not enable. A table that does not exist is found first, and a query block of a
     * common table expression no table reference names is never prepared (verified on a live 8.4
     * server).
     * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html,
     * https://dev.mysql.com/doc/refman/8.4/en/group-by-modifiers.html.
     *
     * @throws SqlError When the statement has such a clause
     */
    public function prepared(Operation $operation, Session $session): void
    {
        $special = static fn (Node $node): bool => $node instanceof Select && ($node->groupBy?->modifier === GroupingModifier::Cube || $node->qualify !== null);
        if (array_filter((new Walker())->find($operation->statement, Select::class), $special) === []) {
            return;
        }
        $reached = $this->reached($operation->statement);
        $blocks = array_values(array_filter($reached, $special));
        $cube = array_filter($blocks, static fn (Node $block): bool => $block instanceof Select && $block->groupBy?->modifier === GroupingModifier::Cube) !== [];
        $qualify = array_filter($blocks, static fn (Node $block): bool => $block instanceof Select && $block->qualify !== null) !== [];
        if (!$cube && !$qualify) {
            return;
        }
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof MissingTable) {
                throw $this->error($diagnostic, $session);
            }
        }
        $names = array_map(static fn (CommonTableExpression $table): string => $table->name->value, (new Walker())->find($operation->statement, CommonTableExpression::class));
        $tables = array_filter($reached, static fn (Node $node): bool => ($node instanceof TableReference || $node instanceof ExplicitTable)
            && ($node->name()->schema !== null || !in_array($node->name()->name->value, $names, true)));
        if ($cube && $tables === []) {
            throw ErrorCode::FeatureNotSupported->error('CUBE');
        }
        if ($qualify) {
            throw ErrorCode::HypergraphRequired->error('QUALIFY clause');
        }
    }

    /**
     * Answers the nodes of a statement the server prepares: all but those of a common table expression that no table reference outside it names.
     *
     * @return list<Node>
     */
    public function reached(Node $statement): array
    {
        $references = array_filter((new Walker())->find($statement, Node::class), static fn (Node $node): bool => ($node instanceof TableReference || $node instanceof ExplicitTable) && $node->name()->schema === null);
        $skipped = [];
        foreach ((new Walker())->find($statement, CommonTableExpression::class) as $table) {
            $inside = array_fill_keys(array_map(spl_object_id(...), (new Walker())->find($table, Node::class)), true);
            $used = array_filter($references, static fn (TableReference|ExplicitTable $reference): bool => $reference->name()->name->value === $table->name->value && !isset($inside[spl_object_id($reference)]));
            if ($used === []) {
                $skipped += $inside;
            }
        }

        return array_values(array_filter((new Walker())->find($statement, Node::class), static fn (Node $node): bool => !isset($skipped[spl_object_id($node)])));
    }

    /**
     * Answers the first common table name a WITH clause defines twice, in the order the server parses the definitions: each after the definitions nested in it.
     *
     * The server checks the names while it parses the statement, so a WITH clause inside a common
     * table expression that is never used reports its duplicate too.
     */
    public function repeated(Node $node): ?Name
    {
        $seen = [];
        $properties = get_object_vars($node);
        $children = [];
        array_walk_recursive($properties, static function ($value) use (&$children): void {
            if ($value instanceof Node) {
                $children[] = $value;
            }
        });
        foreach ($children as $child) {
            $found = $this->repeated($child);
            if ($found !== null) {
                return $found;
            }
            if ($node instanceof With && $child instanceof CommonTableExpression) {
                if (isset($seen[$child->name->value])) {
                    return $child->name;
                }
                $seen[$child->name->value] = true;
            }
        }

        return null;
    }

    /**
     * Tells whether the server reports a warning only once it has read the whole statement, so that a problem found while reading leaves it out.
     */
    public static function afterReading(Warning $warning): bool
    {
        return $warning instanceof Deprecation && $warning->construct === Deprecated::IntoInsideQuery;
    }

    /**
     * Tells whether the server reports a problem while it parses the statement, before it opens any table.
     */
    public static function parsed(Diagnostic $diagnostic): bool
    {
        return $diagnostic instanceof WrongArgumentCount || $diagnostic instanceof NamedArgument || $diagnostic instanceof ReservedFunction
            || ($diagnostic instanceof NotSupportedYet && $diagnostic->feature === 'AT LOCAL');
    }

    /**
     * Tells whether the server reports a problem while it reads the end of a query block: a LIMIT operand naming an undeclared variable, or a locking clause naming a table the block lacks or locking a table twice.
     *
     * A table alias the block uses twice is found before them, when the server adds the tables of the FROM clause.
     */
    public static function closing(Diagnostic $diagnostic): bool
    {
        return $diagnostic instanceof UndeclaredVariable
            || ($diagnostic instanceof Misuse && ($diagnostic->rule === MisuseRule::UnknownLockedTable || $diagnostic->rule === MisuseRule::RepeatedLockedTable));
    }

    /**
     * Tells whether the server reports a problem only after it has resolved every name of the statement.
     */
    public static function late(Diagnostic $diagnostic): bool
    {
        return $diagnostic instanceof Misuse && $diagnostic->rule === MisuseRule::DuplicateWindow;
    }

    /**
     * Tells whether the command of a statement reports a problem itself, in its rows or in its own order of checks.
     *
     * A table maintenance or key cache statement reports a missing table, and a histogram request
     * naming several tables, in its rows. A data definition statement checks the tables and
     * columns it changes as the server does, while it runs; ALTER TABLE, CREATE INDEX and DROP
     * INDEX open the table before they resolve the expressions of their key parts and defaults
     * (verified on a live 8.4 server).
     */
    public static function answered(Node $statement, Diagnostic $diagnostic): bool
    {
        $administration = $statement instanceof CheckTable || $statement instanceof OptimizeTable || $statement instanceof RepairTable || $statement instanceof AnalyzeTable
            || $statement instanceof CacheIndex || $statement instanceof LoadIndex || $statement instanceof ChecksumTable;
        if ($administration) {
            return $diagnostic instanceof MissingTable || $diagnostic instanceof HistogramTables || $diagnostic instanceof UnknownHistogramColumn;
        }
        if ($statement instanceof AlterTable || $statement instanceof CreateIndex || $statement instanceof DropIndex) {
            return true;
        }
        if ($statement instanceof RenameTable) {
            return $diagnostic instanceof MissingTable || $diagnostic instanceof TableExists;
        }
        if ($statement instanceof CreateTableLike || $statement instanceof LockTables || $statement instanceof HandlerOpen) {
            return $diagnostic instanceof MissingTable;
        }
        if ($statement instanceof LoadTable) {
            return $diagnostic instanceof MissingTable || $diagnostic instanceof MissingColumn || $diagnostic instanceof UnpartitionedTable || $diagnostic instanceof UnknownPartition;
        }
        if ((new \MySqlMemory\Command\Show\Inspection())->inspects($statement)) {
            return $diagnostic instanceof MissingTable;
        }
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowParseTree) {
            return $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityMisuse;
        }

        return false;
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
     * Answers the error of a clock call whose precision is above 6, named as the server names the function.
     *
     * Source: https://dev.mysql.com/doc/refman/8.4/en/fractional-seconds.html.
     */
    public function precision(ClockCall $call): SqlError
    {
        $name = match ($call->clock) {
            Clock::Now => 'now',
            Clock::CurrentTime => 'curtime',
            Clock::SystemDate => 'sysdate',
            Clock::UtcTime => 'utc_time',
            Clock::UtcTimestamp => 'utc_timestamp',
            Clock::CurrentDate => 'curdate',
            Clock::UtcDate => 'utc_date',
        };

        return ErrorCode::TooBigPrecision->error($call->decimals(), $name, 6);
    }

    /**
     * Answers the error of a call of a stored function that does not exist, in the database the call names or the current one.
     */
    public function routine(FunctionCall $call, Session $session): SqlError
    {
        $database = $session->variables->database;
        if ($call->schema === null && $database === '') {
            return ErrorCode::NoDatabase->error();
        }

        $function = $session->instance->dictionary->schema($call->schema->value ?? $database)->functions[strtolower($call->name->value)] ?? null;
        if ($function !== null) {
            $count = count($function->statement->parameters->parameters);

            return $count === count($call->arguments) ? ErrorCode::NotSupportedYet->error('calls of stored functions') : ErrorCode::RoutineArgumentCount->error('FUNCTION', $function->schema . '.' . $function->name, $count, count($call->arguments));
        }

        return ErrorCode::RoutineMissing->error('FUNCTION', ($call->schema->value ?? $database) . '.' . $call->name->value);
    }

    /**
     * Answers the server error of a diagnostic, for a name read in a clause of a statement.
     */
    public function error(Diagnostic $diagnostic, Session $session, string $clause = 'field list', ?Node $statement = null): SqlError
    {
        $database = $session->variables->database;

        return match (true) {
            $diagnostic instanceof MissingTable => $diagnostic->name->schema === null && $database === '' ? ErrorCode::NoDatabase->error() : ErrorCode::NoSuchTable->error($diagnostic->name->schema->value ?? $database, $diagnostic->name->name->value),
            $diagnostic instanceof MissingColumn => ErrorCode::BadField->error(($diagnostic->qualifier?->schema === null ? '' : $diagnostic->qualifier->schema->value . '.') . ($diagnostic->qualifier === null ? '' : $diagnostic->qualifier->name->value . '.') . $diagnostic->name->value, $this->joining($diagnostic, $statement) ? 'from clause' : $clause),
            $diagnostic instanceof AmbiguousColumn => ErrorCode::NonUniqueColumn->error($diagnostic->name->value, $clause),
            $diagnostic instanceof AmbiguousAlias => ErrorCode::NonUniqueColumn->error($diagnostic->name->value, $clause),
            $diagnostic instanceof OrdinalOutOfRange => ErrorCode::BadField->error((string) $diagnostic->position, $clause === 'group statement' ? $clause : 'order clause'),
            $diagnostic instanceof NonUniqueTable => ErrorCode::NonUniqueTable->error($diagnostic->alias->value),
            $diagnostic instanceof UndeclaredVariable => ErrorCode::UndeclaredVariable->error($diagnostic->name->value),
            $diagnostic instanceof RepeatedTable => ErrorCode::NonUniqueTable->error($diagnostic->name->name->value),
            $diagnostic instanceof ValueCountMismatch => $diagnostic->row === null ? ErrorCode::WrongValueCount->error() : ErrorCode::WrongValueCountOnRow->error($diagnostic->row),
            $diagnostic instanceof OperandColumns => ErrorCode::OperandColumns->error($diagnostic->expected),
            $diagnostic instanceof WrongArgumentCount => ErrorCode::WrongParameterCountToNativeFunction->error($diagnostic->function->value),
            $diagnostic instanceof NamedArgument => ErrorCode::WrongParametersToNativeFunction->error(strtolower($diagnostic->function->value)),
            $diagnostic instanceof ReservedFunction => ErrorCode::NativeFunctionRejected->error($diagnostic->function->value),
            $diagnostic instanceof UnsupportedWindowing => ErrorCode::NotSupportedYet->error($diagnostic->limit->value),
            $diagnostic instanceof MultiplePrimaryKeys => ErrorCode::MultiplePrimaryKey->error(),
            $diagnostic instanceof NoColumns => ErrorCode::TableMustHaveColumns->error(),
            $diagnostic instanceof DuplicateColumn => ErrorCode::DuplicateFieldName->error($diagnostic->column->value),
            $diagnostic instanceof IncorrectColumnName => ErrorCode::WrongColumnName->error($diagnostic->column->value),
            $diagnostic instanceof NullablePrimaryKey => ErrorCode::PrimaryCantHaveNull->error(),
            $diagnostic instanceof UnknownKeyColumn => ErrorCode::KeyColumnMissing->error($diagnostic->column->value),
            $diagnostic instanceof UnknownColumn => ErrorCode::FieldNotFoundInPartitionFunction->error(),
            $diagnostic instanceof TableExists => ErrorCode::TableExists->error($diagnostic->name->name->value),
            $diagnostic instanceof DuplicateWrittenColumn => ErrorCode::FieldSpecifiedTwice->error($diagnostic->column->value),
            $diagnostic instanceof GeneratedColumnWrite => ErrorCode::GeneratedColumnValue->error($diagnostic->column->value, $diagnostic->table->value),
            $diagnostic instanceof WriteMisuse => $this->write($diagnostic, $statement),
            $diagnostic instanceof NotSupportedYet => ErrorCode::NotSupportedYet->error($diagnostic->feature),
            $diagnostic instanceof Misuse => $this->misuse($diagnostic),
            $diagnostic instanceof NonGroupedColumn => new SqlError(match ($diagnostic->rule) {
                GroupingRule::NotDetermined => ErrorCode::WrongFieldWithGroup,
                GroupingRule::WithoutGroupBy => ErrorCode::MixOfGroupFunctionAndFields,
                GroupingRule::NotSelected => ErrorCode::FieldInOrderNotSelect,
            }, $diagnostic->message()),
            $diagnostic instanceof UnknownQualifier => ErrorCode::BadTable->error(($diagnostic->table->schema === null ? '' : $diagnostic->table->schema->value . '.') . $diagnostic->table->name->value),
            $diagnostic instanceof UnknownDeleteTable => ErrorCode::UnknownTable->error($diagnostic->table->name->value, 'MULTI DELETE'),
            $diagnostic instanceof CountMismatch => match ($diagnostic->list) {
                CountedList::SetOperands, CountedList::IntoVariables => ErrorCode::WrongNumberOfColumnsInSelect->error(),
                CountedList::ValueRows => ErrorCode::WrongValueCountOnRow->error($diagnostic->row),
                CountedList::DerivedColumns => ErrorCode::ViewWrongList->error(),
            },
            $diagnostic instanceof IllegalCollationMix => new SqlError(match (count($diagnostic->operands)) {
                2 => ErrorCode::CantAggregateTwoCollations,
                3 => ErrorCode::CantAggregateThreeCollations,
                default => ErrorCode::CantAggregateCollations,
            }, $diagnostic->message()),
            $diagnostic instanceof UnpartitionedTable => ErrorCode::PartitionClauseOnNonpartitioned->error(),
            $diagnostic instanceof UnknownPartition => ErrorCode::UnknownPartition->error($diagnostic->partition, $diagnostic->table),
            $diagnostic instanceof UnknownSystemVariable => ErrorCode::UnknownSystemVariable->error($diagnostic->name),
            $diagnostic instanceof VariableMisuse => new SqlError(ErrorCode::from($diagnostic->rule->code()), $diagnostic->message()),
            $diagnostic instanceof UnknownCollation => ErrorCode::UnknownCollation->error($diagnostic->name),
            $diagnostic instanceof UnknownAlterChoice => ($diagnostic->lock ? ErrorCode::UnknownAlterLock : ErrorCode::UnknownAlterAlgorithm)->error($diagnostic->name->value),
            $diagnostic instanceof BucketCountOutOfRange => ErrorCode::DataOutOfRange->error('Number of buckets', 'ANALYZE TABLE'),
            $diagnostic instanceof UnknownCharset => ErrorCode::UnknownCharacterSet->error($diagnostic->name),
            $diagnostic instanceof CollationMismatch => ErrorCode::CollationCharsetMismatch->error($diagnostic->collation, $diagnostic->charset),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem => (new \MySqlMemory\Error\ProgramErrors())->error($diagnostic),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\WrongRelationKind => (new \MySqlMemory\Error\ProgramErrors())->relation($diagnostic, $database),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\ViewColumnCount => ErrorCode::ViewWrongList->error(),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityMisuse && $diagnostic->rule === \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityRule::DebugOnly => ErrorCode::FeatureDisabled->error('SHOW PROCEDURE|FUNCTION CODE', '--with-debug'),
            \MySqlMemory\Command\Admin\Refusals::handles($diagnostic) => (new \MySqlMemory\Command\Admin\Refusals())->error($diagnostic, $statement, $session->text),
            default => new SqlError(ErrorCode::UnknownError, $diagnostic->message()),
        };
    }

    /**
     * Answers the server error of a rule a data manipulation statement breaks.
     */
    public function write(WriteMisuse $misuse, ?Node $statement): SqlError
    {
        return match ($misuse->rule) {
            WriteRule::DefaultOutsideInsert => ErrorCode::ValuesDefault->error(),
            WriteRule::OrderedMultipleUpdate => ErrorCode::WrongUsage->error('UPDATE', 'ORDER BY'),
            WriteRule::LimitedMultipleUpdate => ErrorCode::WrongUsage->error('UPDATE', 'LIMIT'),
            WriteRule::WildcardColumn => ErrorCode::BadField->error('*', 'field list'),
            WriteRule::CommonTableTarget, WriteRule::NonUpdatableTarget => ErrorCode::NonUpdatableTable->error($misuse->table->value ?? '', $statement instanceof Update ? 'UPDATE' : 'DELETE'),
        };
    }

    /**
     * Tells whether a missing column is a column a USING list of the statement names, which the server reports in the FROM clause (verified on a live 8.4 server).
     */
    public function joining(MissingColumn $diagnostic, ?Node $statement): bool
    {
        foreach ($statement === null ? [] : (new Walker())->find($statement, JoinedTable::class) as $join) {
            if (in_array($diagnostic->name, $join->using, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the server error of a broken rule of a query.
     */
    public function misuse(Misuse $misuse): SqlError
    {
        $name = match (true) {
            $misuse->name instanceof Name => $misuse->name->value,
            $misuse->name instanceof QualifiedName => $misuse->name->name->value,
            default => '',
        };

        return match ($misuse->rule) {
            MisuseRule::StarWithoutTables => ErrorCode::NoTablesUsed->error(),
            MisuseRule::DerivedWithoutAlias => ErrorCode::DerivedMustHaveAlias->error(),
            MisuseRule::TableFunctionWithoutAlias => ErrorCode::TableFunctionWithoutAlias->error(),
            MisuseRule::DuplicateAlias, MisuseRule::DuplicateCommonTable => ErrorCode::NonUniqueTable->error($name),
            MisuseRule::RecursiveWithoutUnion => ErrorCode::RecursiveRequiresUnion->error($name),
            MisuseRule::RecursiveWithoutAnchor => ErrorCode::RecursiveRequiresNonrecursiveFirst->error($name),
            MisuseRule::DuplicateColumn => ErrorCode::DuplicateFieldName->error($name),
            MisuseRule::DuplicateWindow => ErrorCode::WindowDefinedTwice->error($name),
            MisuseRule::UnknownWindow => ErrorCode::WindowNotDefined->error($name),
            MisuseRule::UnknownLockedTable => ErrorCode::UnresolvedLockedTable->error($misuse->name instanceof QualifiedName && $misuse->name->schema !== null ? '`' . $misuse->name->schema->value . '`.`' . $name . '`' : '`' . $name . '`'),
            MisuseRule::RepeatedLockedTable => ErrorCode::DuplicateLockedTable->error(match (true) {
                $misuse->name instanceof QualifiedName && $misuse->name->schema !== null => '`' . $misuse->name->schema->value . '`.`' . $name . '`',
                $misuse->name instanceof QualifiedName => '`' . $name . '`',
                default => $name,
            }),
            MisuseRule::AmbiguousJoinColumn => ErrorCode::NonUniqueColumn->error($name, 'from clause'),
            MisuseRule::EmptyValuesRow => ErrorCode::ValuesEmptyRow->error(),
        };
    }
}
