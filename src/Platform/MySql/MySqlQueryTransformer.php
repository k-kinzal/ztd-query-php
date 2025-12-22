<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\QueryGuard;
use ZtdQuery\QueryTransformer;
use ZtdQuery\Rewrite\Projection\WriteProjection;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\Shadowing\CteShadowing;
use ZtdQuery\Schema\SchemaRegistry;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Platform\MySql\Transformer\CteGenerator;
use ZtdQuery\Platform\MySql\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Transformer\UpdateTransformer;
use RuntimeException;

/**
 * Legacy-compatible transformer wrapper using the new rewrite pipeline.
 */
final class MySqlQueryTransformer implements QueryTransformer
{
    /**
     * Guard used to classify and block SQL.
     *
     * @var QueryGuard
     */
    private QueryGuard $guard;

    /**
     * Schema registry for column lookup.
     *
     * @var SchemaRegistry
     */
    private SchemaRegistry $schemaRegistry;

    /**
     * CTE generator used in shadowing.
     *
     * @var CteGenerator
     */
    private CteGenerator $cteGenerator;

    /**
     * UPDATE projector for write projection.
     *
     * @var UpdateTransformer
     */
    private UpdateTransformer $updateTransformer;

    /**
     * DELETE projector for write projection.
     *
     * @var DeleteTransformer
     */
    private DeleteTransformer $deleteTransformer;

    /**
     * @param QueryGuard|null $guard Query guard for classification.
     * @param SchemaRegistry|null $schemaRegistry Schema cache for column lookup.
     * @param CteGenerator|null $cteGenerator CTE generator for shadowing.
     * @param UpdateTransformer|null $updateTransformer UPDATE projector.
     * @param DeleteTransformer|null $deleteTransformer DELETE projector.
     */
    public function __construct(
        ?QueryGuard $guard = null,
        ?SchemaRegistry $schemaRegistry = null,
        ?CteGenerator $cteGenerator = null,
        ?UpdateTransformer $updateTransformer = null,
        ?DeleteTransformer $deleteTransformer = null
    ) {
        $this->guard = $guard ?? new QueryGuard();
        $this->schemaRegistry = $schemaRegistry ?? new SchemaRegistry();
        $this->cteGenerator = $cteGenerator ?? new CteGenerator();
        $this->updateTransformer = $updateTransformer ?? new UpdateTransformer();
        $this->deleteTransformer = $deleteTransformer ?? new DeleteTransformer();
    }

    /**
     * {@inheritDoc}
     */
    public function transform(string $sql, array $tableData): string
    {
        $shadowStore = new ShadowStore();
        foreach ($tableData as $tableName => $rows) {
            $shadowStore->set($tableName, $rows);
        }

        $shadowing = new CteShadowing($this->cteGenerator, $this->schemaRegistry);
        $writeProjection = new WriteProjection(
            $shadowStore,
            $this->schemaRegistry,
            $shadowing,
            $this->updateTransformer,
            $this->deleteTransformer
        );

        $rewriter = new MySqlRewriter($this->guard, $shadowStore, $shadowing, $writeProjection);
        $plan = $rewriter->rewrite($sql);

        if ($plan->kind() === QueryKind::FORBIDDEN) {
            throw new RuntimeException('ZTD Write Protection: Unsupported or unsafe SQL statement.');
        }

        if ($plan->kind() === QueryKind::UNKNOWN_SCHEMA) {
            throw new RuntimeException(sprintf(
                'ZTD Write Protection: Unknown table referenced: %s',
                $plan->unknownIdentifier() ?? 'unknown'
            ));
        }

        return $plan->sql();
    }
}
