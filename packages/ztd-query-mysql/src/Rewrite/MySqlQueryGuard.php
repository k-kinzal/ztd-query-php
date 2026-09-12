<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

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
use ZtdQuery\Rewrite\QueryKind;

/**
 * Classifies SQL and enforces ZTD write-protection rules.
 */
final class MySqlQueryGuard
{
    private MySqlParser $parser;

    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(MySqlParser $parser)
    {
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
            $kind = (new Rewrite\Classification\CteStatementKind())->classifyWithFallback($sql);
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
     * Classify a parsed statement into its QueryKind, or null if unsupported.
     */
    public function classifyStatement(Statement $statement): ?QueryKind
    {
        if ($statement instanceof SelectStatement) {
            if ($statement->into !== null) {
                return null;
            }
            return QueryKind::READ;
        }

        if ($statement instanceof UpdateStatement || $statement instanceof DeleteStatement || $statement instanceof InsertStatement || $statement instanceof TruncateStatement || $statement instanceof ReplaceStatement || $statement instanceof LoadStatement) {
            return QueryKind::WRITE_SIMULATED;
        }

        if ($statement instanceof CreateStatement || $statement instanceof DropStatement || $statement instanceof AlterStatement) {
            return $statement->options !== null && $statement->options->has('TABLE') !== false
                ? QueryKind::DDL_SIMULATED
                : null;
        }

        if ($statement instanceof WithStatement) {
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

        return null;
    }

}
