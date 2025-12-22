<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

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

/**
 * Classifies SQL and enforces ZTD write-protection rules.
 */
final class MySqlQueryGuard
{
    private MySqlParser $parser;

    public function __construct(MySqlParser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Classify a SQL string into READ/WRITE_SIMULATED/DDL_SIMULATED or null if unsupported.
     */
    public function classify(string $sql): ?QueryKind
    {
        $statements = $this->parser->parse($sql);
        if ($statements === [] || count($statements) !== 1) {
            return null;
        }

        $statement = $statements[0];
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
     */
    public function assertAllowed(Statement|string $input): void
    {
        $kind = is_string($input) ? $this->classify($input) : $this->classifyStatement($input);
        if ($kind === null) {
            throw new \RuntimeException('ZTD Write Protection: Unsupported or unsafe SQL statement.');
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

        if ($statement instanceof UpdateStatement || $statement instanceof DeleteStatement || $statement instanceof InsertStatement || $statement instanceof TruncateStatement || $statement instanceof ReplaceStatement) {
            return QueryKind::WRITE_SIMULATED;
        }

        if ($statement instanceof CreateStatement) {
            if ($statement->options !== null && self::optionSet($statement->options, 'TABLE')) {
                return QueryKind::DDL_SIMULATED;
            }
            return null;
        }

        if ($statement instanceof DropStatement) {
            if ($statement->options !== null && self::optionSet($statement->options, 'TABLE')) {
                return QueryKind::DDL_SIMULATED;
            }
            return null;
        }

        if ($statement instanceof AlterStatement) {
            if ($statement->options !== null && self::optionSet($statement->options, 'TABLE')) {
                return QueryKind::DDL_SIMULATED;
            }
            return null;
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

    private function classifyWithFallback(string $sql): ?QueryKind
    {
        $upper = strtoupper($sql);
        $len = strlen($upper);
        $depth = 0;
        $seenCteBody = false;
        $quote = '';

        for ($i = 0; $i < $len; $i++) {
            $char = $upper[$i];

            if ($quote !== '') {
                if ($char === $quote) {
                    $prev = $i > 0 ? $upper[$i - 1] : '';
                    if ($quote === '`' || $prev !== '\\') {
                        $quote = '';
                    }
                }
                continue;
            }

            if ($char === '\'' || $char === '"' || $char === '`') {
                $quote = $char;
                continue;
            }

            if ($char === '(') {
                $depth++;
                $seenCteBody = true;
                continue;
            }

            if ($char === ')') {
                if ($depth > 0) {
                    $depth--;
                }
                continue;
            }

            if (!$seenCteBody || $depth !== 0 || !ctype_alpha($char)) {
                continue;
            }

            $prev = $i > 0 ? $upper[$i - 1] : '';
            if (ctype_alpha($prev)) {
                continue;
            }

            $j = $i;
            while ($j < $len && ctype_alpha($upper[$j])) {
                $j++;
            }

            $keyword = substr($upper, $i, $j - $i);
            $kind = match ($keyword) {
                'SELECT' => QueryKind::READ,
                'UPDATE', 'DELETE', 'INSERT', 'REPLACE', 'TRUNCATE' => QueryKind::WRITE_SIMULATED,
                'CREATE', 'DROP', 'ALTER' => QueryKind::DDL_SIMULATED,
                default => null,
            };

            if ($kind !== null) {
                return $kind;
            }

            $i = $j - 1;
        }

        return null;
    }

    /**
     * Check whether the given OptionsArray has a specific option set.
     *
     * @param \PhpMyAdmin\SqlParser\Components\OptionsArray $options
     */
    private static function optionSet(\PhpMyAdmin\SqlParser\Components\OptionsArray $options, string $name): bool
    {
        return $options->has($name) !== false;
    }
}
