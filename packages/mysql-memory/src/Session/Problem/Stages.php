<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem;

use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\DropIndex;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\TableExists;
use SqlSemantics\Platform\MySql\Statement\Alter\RenameTable;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\NamedArgument;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\ReservedFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerOpen;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UndeclaredVariable;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UnknownPartition;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UnpartitionedTable;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CacheIndex;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\LoadIndex;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\LockTables;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\AnalyzeTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\ChecksumTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\CheckTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\OptimizeTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\RepairTable;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\HistogramTables;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\UnknownHistogramColumn;
use SqlSemantics\Platform\MySql\Statement\Table\CreateIndex;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTableLike;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\Warning;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Table\MissingTable;

/**
 * Tells at which stage of checking a statement the server reports a problem, and which statements report their problems themselves.
 *
 * The server reports the problems it finds while it parses a statement first, then those it finds
 * at the end of each query block, then the names it resolves, and the windows a query block
 * defines twice last. The command of an account, maintenance, key cache or data definition
 * statement checks the tables and columns it names itself, in its own order or in its rows
 * (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/table-maintenance-statements.html.
 *
 * @visibility MySqlMemory
 */
final class Stages
{
    /**
     * Retains modern contextualization conditions after grammar interpretation of their instruction.
     * Legacy conditions retain their existing order while grammar actions are completed.
     * A later stored instruction cannot prevent an earlier instruction from contextualizing.
     *
     * @param array<int, Warning> $warnings The semantic notices in written order
     * @return array<int, Warning> The conditions from stages that the input reaches
     */
    public static function grammar(array $warnings, bool $legacy, ?\SqlSemantics\Statement\Fact\Facts $facts = null): array
    {
        $warnings = array_filter($warnings, static fn (Warning $warning): bool => !$warning instanceof Deprecation || $warning->phase() !== \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Resolution);
        if ($legacy) {
            return $warnings;
        }
        $failed = [];
        foreach ($warnings as $warning) {
            if ($warning instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure && $warning->phase === \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Syntax) {
                $instruction = $facts?->warningInstruction($warning);
                $failed[$instruction === null ? 0 : spl_object_id($instruction)] = true;
            }
        }
        return array_filter($warnings, static fn (Warning $warning): bool => !isset($failed[($instruction = $facts?->warningInstruction($warning)) === null ? 0 : spl_object_id($instruction)]) || match (true) {
            $warning instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure => $warning->phase === \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Syntax,
            $warning instanceof Deprecation => $warning->phase() === \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Syntax,
            default => true,
        });
    }

    /**
     * Tells whether the command of a statement raises every problem of it itself, before any stored program is checked: an account statement, INSTALL COMPONENT and FLUSH TABLES.
     */
    public static function selfChecked(\SqlSemantics\Statement\Statement $statement): bool
    {
        return \MySqlMemory\Command\Account\Names::owns($statement) || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallComponent || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushTables
            || (($statement instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateTable || $statement instanceof CreateIndex || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Update) && $statement->hasExecutionCondition());
    }

    /**
     * Table requests with intrinsic conditions prepare their operands in definition order.
     */
    public static function definitionCondition(Node $statement): bool
    {
        return ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateTable || $statement instanceof AlterTable || $statement instanceof CreateIndex) && $statement->hasExecutionCondition();
    }

    /**
     * Tells whether a statement opens its table before it resolves the expressions of its key parts and defaults: ALTER TABLE, CREATE INDEX and DROP INDEX.
     */
    public static function opensTableFirst(Node $statement): bool
    {
        return $statement instanceof AlterTable || $statement instanceof CreateIndex || $statement instanceof DropIndex
            || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexSeek
            || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexRead
            || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan;
    }

    /**
     * Tells whether a statement is a table maintenance or key cache statement, which reports a missing table in its rows.
     */
    public static function administers(Node $statement): bool
    {
        return $statement instanceof CheckTable || $statement instanceof OptimizeTable || $statement instanceof RepairTable || $statement instanceof AnalyzeTable
            || $statement instanceof CacheIndex || $statement instanceof LoadIndex || $statement instanceof ChecksumTable;
    }

    /**
     * Tells whether a statement is EXPLAIN, EXPLAIN FOR CONNECTION or SHOW PARSE_TREE, which refuses a misuse of itself in its own order.
     */
    public static function explains(Node $statement): bool
    {
        return $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowParseTree;
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
     *
     * A system variable the server does not know is one of them (verified on live 8.0, 8.4 and 9.1
     * servers), and in MySQL 5.6 and 5.7 one written with an instance that is not structured.
     */
    public static function parsed(Diagnostic $diagnostic): bool
    {
        return ($diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem && in_array($diagnostic->rule, [\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule::IncorrectName, \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule::NameTooLong], true)) || $diagnostic instanceof WrongArgumentCount || $diagnostic instanceof NamedArgument || $diagnostic instanceof ReservedFunction
            || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\SelectOptionConflict
            || $diagnostic instanceof UnknownSystemVariable || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnstructuredVariable
            || ($diagnostic instanceof NotSupportedYet && in_array($diagnostic->feature, ['AT LOCAL', 'LIMIT & IN/ALL/ANY/SOME subquery'], true))
            || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Server\Problem\FractionalNumber
            || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialProblem;
    }

    /**
     * Tells whether the server reports a problem while it reads the end of a query block: a LIMIT operand naming an undeclared variable, or a locking clause naming a table the block lacks or locking a table twice.
     *
     * A table alias the block uses twice is found before them, when the server adds the tables of the FROM clause.
     */
    public static function closing(Diagnostic $diagnostic): bool
    {
        return $diagnostic instanceof UndeclaredVariable
            || ($diagnostic instanceof Misuse && $diagnostic->rule === MisuseRule::RecursiveReferencePosition)
            || ($diagnostic instanceof NotSupportedYet && $diagnostic->feature === \SqlSemantics\Platform\MySql\Statement\Server\Instance\Kill::DEPENDENCIES)
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
     * INDEX open the table before they resolve the expressions of their key parts and defaults;
     * CREATE TABLE reports a column its generated columns, expression defaults and CHECK
     * constraints read that it lacks, each with its own error, and a table a foreign key
     * references that does not exist (verified on a live 8.4 server).
     */
    public static function answered(Node $statement, Diagnostic $diagnostic): bool
    {
        if (self::administers($statement)) {
            return $diagnostic instanceof MissingTable || $diagnostic instanceof HistogramTables || $diagnostic instanceof UnknownHistogramColumn;
        }
        if (self::opensTableFirst($statement)) {
            return true;
        }
        if (self::viewTarget($statement, $diagnostic)) {
            return true;
        }
        if ($statement instanceof RenameTable) {
            return $diagnostic instanceof MissingTable || $diagnostic instanceof TableExists;
        }
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateTable && $statement->query === null) {
            return self::tableDefinition($statement, $diagnostic);
        }
        if ($statement instanceof CreateTableLike || $statement instanceof LockTables || $statement instanceof HandlerOpen) {
            return $diagnostic instanceof MissingTable;
        }
        if ($statement instanceof LoadTable) {
            return $diagnostic instanceof MissingTable || $diagnostic instanceof MissingColumn || $diagnostic instanceof UnpartitionedTable || $diagnostic instanceof UnknownPartition || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadConflict;
        }
        if ((new \MySqlMemory\Command\Show\Inspection())->inspects($statement)) {
            return $diagnostic instanceof MissingTable;
        }
        if (self::explains($statement)) {
            return $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityMisuse;
        }

        return false;
    }

    /**
     * Identifies a view target's kind check, which follows preparation of its query.
     */
    public static function viewTarget(Node $statement, Diagnostic $diagnostic): bool
    {
        return $statement instanceof \SqlSemantics\Platform\MySql\Statement\View\CreateView && $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\WrongRelationKind && $diagnostic->name === $statement->definition->name;
    }

    /**
     * Defers definition diagnostics to the CREATE TABLE command, which checks constraints and empty definitions in release order.
     */
    public static function tableDefinition(\SqlSemantics\Platform\MySql\Statement\Table\CreateTable $statement, Diagnostic $diagnostic): bool
    {
        if ((new Definition\FunctionalQueries())->deferred($diagnostic, $statement)) {
            return true;
        }
        return $diagnostic instanceof MissingColumn || $diagnostic instanceof MissingTable || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\NoColumns
            || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\UnknownKeyColumn;
    }

    /**
     * Tells whether the planner checks a diagnostic after resolving every name and window.
     *
     * IGNORE NULLS and FROM LAST are checked with window function arguments. Unsupported frames
     * are checked after resolving names and refusing misplaced calls. Aggregate diagnostics with
     * an owning expression are located in their own window by Locations. A RANGE
     * boundary constraint remains after its ordering type and runtime offset checks.
     * ROWS with interval or parameter operands likewise prepares those inputs first
     * (verified on MySQL 8.0.44, 8.4.7 and 9.1.0).
     */
    public static function planned(Diagnostic $diagnostic): bool
    {
        if ($diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowConstraint && !$diagnostic->positional) {
            $frame = $diagnostic->window->frame;
            if ($frame?->unit === \SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit::Range) {
                return true;
            }
            foreach ([$frame?->start, $frame?->end] as $bound) {
                if ($bound !== null && ($bound->unit !== null || ($bound->offset !== null && !$bound->offset instanceof \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral))) {
                    return true;
                }
            }
            return false;
        }
        return $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\Window\NestingConstraint || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowRefinement || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowOperandConstraint || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\FrameConstraint || ($diagnostic instanceof UnsupportedWindowing && $diagnostic->expression === null);
    }
    /**
     * Identifies character interpretation failures before relation contextualization.
     */
    public static function characters(Diagnostic $diagnostic, bool $legacy): bool
    {
        return $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset
            || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\InvalidCharacterString
            || ($diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation && $diagnostic->name !== '' && !$legacy);
    }

}
