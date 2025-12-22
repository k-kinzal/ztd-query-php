<?php

declare(strict_types=1);

namespace ZtdQuery;

use ZtdQuery\Platform\MySql\MySqlDialect;
use ZtdQuery\Rewrite\QueryKind;
use PhpMyAdmin\SqlParser\Statement;
use PhpMyAdmin\SqlParser\Statements\AlterStatement;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use PhpMyAdmin\SqlParser\Statements\DropStatement;
use PhpMyAdmin\SqlParser\Statements\InsertStatement;
use PhpMyAdmin\SqlParser\Statements\ReplaceStatement;
use PhpMyAdmin\SqlParser\Statements\SelectStatement;
use PhpMyAdmin\SqlParser\Statements\TruncateStatement;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;
use PhpMyAdmin\SqlParser\Statements\WithStatement;
use RuntimeException;

/**
 * Classifies SQL and enforces ZTD write-protection rules.
 */
final class QueryGuard
{
    /**
     * Classify a SQL string into READ/WRITE_SIMULATED/FORBIDDEN.
     */
    public function classify(string $sql): QueryKind
    {
        $parser = MySqlDialect::createParser($sql);
        if ($parser->statements === [] || count($parser->statements) !== 1) {
            return QueryKind::FORBIDDEN;
        }

        return $this->classifyStatement($parser->statements[0]);
    }

    /**
     * Throw when the SQL or statement is not allowed by the guard.
     *
     * @param Statement|string $input Parsed statement or raw SQL.
     */
    public function assertAllowed(Statement|string $input): void
    {
        $kind = is_string($input) ? $this->classify($input) : $this->classifyStatement($input);
        if ($kind === QueryKind::FORBIDDEN) {
            throw new RuntimeException('ZTD Write Protection: Unsupported or unsafe SQL statement.');
        }
    }

    /**
     * Classify a parsed statement into its QueryKind.
     */
    public function classifyStatement(Statement $statement): QueryKind
    {
        if ($statement instanceof SelectStatement) {
            // SELECT INTO OUTFILE/DUMPFILE/variable are forbidden
            if ($statement->into !== null) {
                return QueryKind::FORBIDDEN;
            }
            return QueryKind::READ;
        }

        if ($statement instanceof UpdateStatement || $statement instanceof DeleteStatement || $statement instanceof InsertStatement || $statement instanceof TruncateStatement || $statement instanceof ReplaceStatement) {
            return QueryKind::WRITE_SIMULATED;
        }

        // DDL statements for virtual schema management
        if ($statement instanceof CreateStatement) {
            // Only support CREATE TABLE / CREATE TEMPORARY TABLE (not CREATE DATABASE, VIEW, INDEX, etc.)
            if ($statement->options !== null && $statement->options->has('TABLE')) {
                return QueryKind::DDL_SIMULATED;
            }
            return QueryKind::FORBIDDEN;
        }

        if ($statement instanceof DropStatement) {
            // Only support DROP TABLE (not DROP DATABASE, VIEW, etc.)
            if ($statement->options !== null && $statement->options->has('TABLE')) {
                return QueryKind::DDL_SIMULATED;
            }
            return QueryKind::FORBIDDEN;
        }

        if ($statement instanceof AlterStatement) {
            // Only support ALTER TABLE (not ALTER DATABASE, VIEW, etc.)
            if ($statement->options !== null && $statement->options->has('TABLE')) {
                return QueryKind::DDL_SIMULATED;
            }
            return QueryKind::FORBIDDEN;
        }

        if ($statement instanceof WithStatement) {
            if ($statement->cteStatementParser === null) {
                return QueryKind::READ;
            }
            $kind = QueryKind::READ;
            foreach ($statement->cteStatementParser->statements as $inner) {
                $innerKind = $this->classifyStatement($inner);
                if ($innerKind === QueryKind::FORBIDDEN) {
                    return QueryKind::FORBIDDEN;
                }
                if ($innerKind === QueryKind::WRITE_SIMULATED) {
                    $kind = QueryKind::WRITE_SIMULATED;
                }
            }
            return $kind;
        }

        return QueryKind::FORBIDDEN;
    }
}
