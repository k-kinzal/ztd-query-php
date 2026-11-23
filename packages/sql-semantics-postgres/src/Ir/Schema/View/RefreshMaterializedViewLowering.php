<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\View;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\View\RefreshMaterializedView as ParsedRefreshMaterializedView;

/**
 * Resolves the materialized target using the declared catalog.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class RefreshMaterializedViewLowering
{
    /**
     * Publishes an ordinary refresh over a known persistent materialized view.
     */
    public function lower(BoundStatement $operation, ParsedRefreshMaterializedView $statement): RefreshMaterializedView
    {
        $context = $operation->context;
        $profile = $context->profile;
        Check::input(
            $profile->parameters === ParameterStyle::Native && $context->relationNames === Comparison::Sensitive,
            'REFRESH MATERIALIZED VIEW requires the native language profile.'
        );
        Check::input(!$statement->concurrently, 'A checked materialized refresh must be nonconcurrent.');
        $resolution = $operation->facts->relation($statement)->table;
        Check::input($resolution instanceof DeclaredTable && $resolution->table->kind === RelationKind::MaterializedView
            && $resolution->table->name->catalog === null, 'REFRESH requires a resolved persistent materialized view.');
        $target = $resolution->table->name;
        $schema = ($target->schema ?? $context->declarationSchema)->value;
        $name = new ViewName(new Identifier($schema), new Identifier($target->name->value));

        return new RefreshMaterializedView($name, $statement->withData ?? true, $profile->grammar);
    }
}
