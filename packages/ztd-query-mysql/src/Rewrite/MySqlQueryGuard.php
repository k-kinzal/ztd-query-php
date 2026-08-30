<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Rewrite;

use PhpMyAdmin\SqlParser\Statement;
use PhpMyAdmin\SqlParser\Statements\AlterStatement;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use PhpMyAdmin\SqlParser\Statements\DropStatement;
use PhpMyAdmin\SqlParser\Statements\InsertStatement;
use PhpMyAdmin\SqlParser\Statements\LoadStatement;
use PhpMyAdmin\SqlParser\Statements\ReplaceStatement;
use PhpMyAdmin\SqlParser\Statements\SelectStatement;
use PhpMyAdmin\SqlParser\Statements\TruncateStatement;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;
use PhpMyAdmin\SqlParser\Statements\WithStatement;
use RuntimeException;
use ZtdQuery\Platform\MySql\Dialect\MySqlStatementOptions;
use ZtdQuery\Platform\MySql\Parse\MySqlParser;
use ZtdQuery\Rewrite\QueryKind;

/**
 * Classifies SQL and enforces ZTD write-protection rules.
 */
final class MySqlQueryGuard
{
    /** @var list<class-string> Statements that write rows, whatever else they do */
    private const WRITE_STATEMENTS = [
        UpdateStatement::class,
        DeleteStatement::class,
        InsertStatement::class,
        TruncateStatement::class,
        ReplaceStatement::class,
        LoadStatement::class,
    ];

    private MySqlParser $parser;

    /**
     * Binds the instance to what it will work from.
     *
     * @param MySqlParser $parser
     */
    public function __construct(
        MySqlParser $parser,
        private readonly MySqlStatementOptions $options = new MySqlStatementOptions(),
        private readonly MySqlTopLevelWords $words = new MySqlTopLevelWords(),
    ) {
        $this->parser = $parser;
    }

    /**
     * Classify a SQL string into READ/WRITE_SIMULATED/DDL_SIMULATED or null if unsupported.
     */
    public function classify(string $sql): ?QueryKind
    {
        if (MySqlReadOnlyDiagnosticStatement::isSafe($sql)) {
            return QueryKind::READ;
        }
        $statement = $this->parser->parseSingleLogicalStatement($sql);
        if ($statement === null) {
            return null;
        }
        if ($statement instanceof WithStatement) {
            $kind = $this->classifyWithFallback($sql);
            if ($kind !== null) {
                return $kind;
            }
        }

        return $this->classifyStatement($statement);
    }

    /**
     * Throw when the SQL or statement is not allowed by the guard.
     *
     * @param Statement|string $input Parsed statement or raw SQL.
     *
     * @throws RuntimeException
     */
    public function assertAllowed(Statement|string $input): void
    {
        $kind = is_string($input) ? $this->classify($input) : $this->classifyStatement($input);
        if ($kind === null) {
            throw new RuntimeException('ZTD Write Protection: Unsupported or unsafe SQL statement.');
        }
    }

    /**
     * Answers what a statement the parser read does, or nothing where ZTD cannot say.
     *
     * @param Statement $statement The statement, as the parser reads it
     *
     * @return QueryKind|null What it does, or null where ZTD cannot simulate it
     */
    public function classifyStatement(Statement $statement): ?QueryKind
    {
        if ($statement instanceof SelectStatement) {
            return $statement->into === null ? QueryKind::READ : null;
        }
        if ($statement instanceof WithStatement) {
            return $this->classifyCteBodies($statement);
        }
        if ($this->isAnyOf($statement, self::WRITE_STATEMENTS)) {
            return QueryKind::WRITE_SIMULATED;
        }
        if ($statement instanceof CreateStatement
            || $statement instanceof DropStatement
            || $statement instanceof AlterStatement
        ) {
            return $this->namesATable($statement) ? QueryKind::DDL_SIMULATED : null;
        }

        return null;
    }

    /**
     * Reports whether the statement is one of these kinds.
     *
     * @param Statement $statement The statement, as the parser reads it
     * @param list<class-string> $kinds Kinds to look for
     *
     * @return bool True when it is one of them
     */
    public function isAnyOf(Statement $statement, array $kinds): bool
    {
        foreach ($kinds as $kind) {
            if ($statement instanceof $kind) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reports whether a definition is one about a table.
     *
     * MySQL writes CREATE, DROP and ALTER of a database, a view, an index and
     * much else besides, and the shadow holds only tables, so what the
     * definition is about is what decides whether ZTD can simulate it.
     *
     * @param AlterStatement|CreateStatement|DropStatement $statement The statement, as the parser reads it
     *
     * @return bool True when it says TABLE
     */
    public function namesATable(AlterStatement|CreateStatement|DropStatement $statement): bool
    {
        return $statement->options !== null && $this->options->isSet($statement->options, 'TABLE');
    }

    /**
     * Answers what a statement written with a WITH prefix does.
     *
     * The bodies of the prefix are statements of their own, and one of them
     * writing is what makes the whole of it a write.
     *
     * @param WithStatement $statement The statement, as the parser reads it
     *
     * @return QueryKind|null What it does, or null where ZTD cannot simulate one of its bodies
     */
    public function classifyCteBodies(WithStatement $statement): ?QueryKind
    {
        if ($statement->cteStatementParser === null) {
            return QueryKind::READ;
        }

        $kind = QueryKind::READ;
        foreach ($statement->cteStatementParser->statements as $inner) {
            $innerKind = $this->classifyStatement($inner);
            if ($innerKind === null) {
                return null;
            }
            if ($innerKind === QueryKind::WRITE_SIMULATED) {
                $kind = QueryKind::WRITE_SIMULATED;
            }
        }

        return $kind;
    }

    /**
     * Answers what a statement the parser could not read is, by reading its words.
     *
     * A WITH whose body the parser refuses still says what it does after the
     * body, and that word is what decides whether the statement writes. Only
     * the word written outside every quote and parenthesis counts.
     *
     * @param string $sql Statement to classify
     *
     * @return QueryKind|null What the statement does, or null where its words say nothing ZTD knows
     */
    public function classifyWithFallback(string $sql): ?QueryKind
    {
        foreach ($this->words->afterBody($sql) as $word) {
            $kind = match ($word) {
                'SELECT' => QueryKind::READ,
                'UPDATE', 'DELETE', 'INSERT', 'REPLACE', 'TRUNCATE' => QueryKind::WRITE_SIMULATED,
                'CREATE', 'DROP', 'ALTER' => QueryKind::DDL_SIMULATED,
                default => null,
            };
            if ($kind !== null) {
                return $kind;
            }
        }

        return null;
    }

}
