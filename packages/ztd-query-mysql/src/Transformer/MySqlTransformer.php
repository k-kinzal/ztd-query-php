<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer;

use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use PhpMyAdmin\SqlParser\Statements\InsertStatement;
use PhpMyAdmin\SqlParser\Statements\ReplaceStatement;
use PhpMyAdmin\SqlParser\Statements\SelectStatement;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;
use PhpMyAdmin\SqlParser\Statements\WithStatement;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Rewrite\SqlTransformer;
use ZtdQuery\Platform\MySql\MySqlCteShadowComposer;

/**
 * Composite SQL transformer for MySQL.
 *
 * Parses the SQL, determines its type, and delegates to the appropriate
 * sub-transformer. DDL and unsupported statements throw UnsupportedSqlException.
 */
final class MySqlTransformer implements SqlTransformer
{
    private MySqlParser $parser;
    private SelectTransformer $selectTransformer;
    private InsertTransformer $insertTransformer;
    private UpdateTransformer $updateTransformer;
    private DeleteTransformer $deleteTransformer;
    private ReplaceTransformer $replaceTransformer;
    private MySqlCteShadowComposer $cteComposer;

    public function __construct(
        MySqlParser $parser,
        SelectTransformer $selectTransformer,
        InsertTransformer $insertTransformer,
        UpdateTransformer $updateTransformer,
        DeleteTransformer $deleteTransformer,
        ReplaceTransformer $replaceTransformer,
    ) {
        $this->parser = $parser;
        $this->selectTransformer = $selectTransformer;
        $this->insertTransformer = $insertTransformer;
        $this->updateTransformer = $updateTransformer;
        $this->deleteTransformer = $deleteTransformer;
        $this->replaceTransformer = $replaceTransformer;
        $this->cteComposer = new MySqlCteShadowComposer();
    }

    /**
     * {@inheritDoc}
     */
    public function transform(string $sql, array $tables): string
    {
        $statements = $this->parser->parse($sql);
        if ($statements === []) {
            throw new UnsupportedSqlException($sql, 'Empty or unparseable');
        }

        $statement = $statements[0];

        if ($statement instanceof WithStatement) {
            $statementSql = $this->cteComposer->statementSql($sql);
            $innerStatements = $this->parser->parse($statementSql);
            $inner = $innerStatements[0] ?? null;
            if ($inner instanceof InsertStatement) {
                return $this->cteComposer->carryPrefix($sql, $this->insertTransformer->transform($statementSql, $tables));
            }
            if ($inner instanceof UpdateStatement) {
                return $this->cteComposer->carryPrefix($sql, $this->updateTransformer->transform($statementSql, $tables));
            }
            if ($inner instanceof DeleteStatement) {
                return $this->cteComposer->carryPrefix($sql, $this->deleteTransformer->transform($statementSql, $tables));
            }

            return $this->selectTransformer->transform($sql, $tables);
        }

        if ($statement instanceof SelectStatement) {
            return $this->selectTransformer->transform($sql, $tables);
        }

        if ($statement instanceof InsertStatement) {
            return $this->insertTransformer->transform($sql, $tables);
        }

        if ($statement instanceof UpdateStatement) {
            return $this->updateTransformer->transform($sql, $tables);
        }

        if ($statement instanceof DeleteStatement) {
            return $this->deleteTransformer->transform($sql, $tables);
        }

        if ($statement instanceof ReplaceStatement) {
            return $this->replaceTransformer->transform($sql, $tables);
        }

        throw new UnsupportedSqlException($sql, 'Statement type not supported by transformer');
    }

    public function commitRewriteState(): void
    {
        $this->insertTransformer->commitRewriteState();
        $this->replaceTransformer->commitRewriteState();
    }
}
