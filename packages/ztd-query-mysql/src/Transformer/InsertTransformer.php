<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer;

use PhpMyAdmin\SqlParser\Statements\InsertStatement;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Platform\MySql\InsertSelectSourceExtractor;
use ZtdQuery\Platform\MySql\MySqlCastRenderer;
use ZtdQuery\Platform\MySql\MySqlCteShadowComposer;
use ZtdQuery\Platform\MySql\MySqlNativeUpsertProjector;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Platform\MySql\MySqlUpsertAssignmentExtractor;
use ZtdQuery\Rewrite\ShadowIdentityAllocator;
use ZtdQuery\Rewrite\SqlTransformer;

/**
 * Transforms INSERT statements into SELECT queries that return the inserted rows.
 * Applies CTE shadowing via the SelectTransformer delegate.
 */
final class InsertTransformer implements SqlTransformer
{
    private MySqlParser $parser;
    private SelectTransformer $selectTransformer;
    private CastRenderer $castRenderer;
    private InsertRowRenderer $rowRenderer;
    private ShadowIdentityAllocator $identityAllocator;
    private InsertSelectRenderer $insertSelectRenderer;
    private MySqlCteShadowComposer $cteComposer;
    private MySqlNativeUpsertProjector $upsertProjector;

    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(
        MySqlParser $parser,
        SelectTransformer $selectTransformer,
        ?CastRenderer $castRenderer = null,
    ) {
        $this->parser = $parser;
        $this->selectTransformer = $selectTransformer;
        $this->castRenderer = $castRenderer ?? new MySqlCastRenderer();
        $this->rowRenderer = new InsertRowRenderer();
        $this->identityAllocator = new ShadowIdentityAllocator();
        $this->insertSelectRenderer = new InsertSelectRenderer();
        $this->cteComposer = new MySqlCteShadowComposer();
        $this->upsertProjector = new MySqlNativeUpsertProjector();
    }

    /**
     * {@inheritDoc}
     * @throws UnsupportedSqlException
     */
    public function transform(string $sql, array $tables): string
    {
        $this->identityAllocator->beginProjection();
        $statements = $this->parser->parse($sql);
        if (!isset($statements[0]) || !$statements[0] instanceof InsertStatement) {
            throw new UnsupportedSqlException($sql, 'Expected INSERT statement');
        }

        $statement = $statements[0];

        $target = Insert\InsertTarget::fromStatement($statement, $tables, $sql);
        $sourceSelectSql = (new InsertSelectSourceExtractor())->extract($sql);
        $selectSql = (new Insert\ResultProjection($this->castRenderer, $this->identityAllocator, $this->insertSelectRenderer, $this->rowRenderer))->buildInsertSelect($statement, $target, $sourceSelectSql);
        $upsertExtractor = new MySqlUpsertAssignmentExtractor();
        $selectSql = $this->upsertProjector->project(
            $selectSql,
            $target->tableName,
            $target->tableColumns,
            $target->candidateKeys,
            $upsertExtractor->extract($sql),
            incomingNamespace: $upsertExtractor->incomingAlias($sql),
        );

        return $this->selectTransformer->transform(
            $this->cteComposer->carryPrefix($sql, $selectSql),
            $tables,
        );
    }

    /**
     * Commit Rewrite State for the supplied MySQL input.
     */
    public function commitRewriteState(): void
    {
        $this->identityAllocator->commitProjection();
    }

}
