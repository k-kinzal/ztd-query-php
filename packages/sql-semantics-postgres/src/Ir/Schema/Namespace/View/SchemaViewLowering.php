<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\View;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateView;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateViewLowering;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Schema\CreateSchema;
use SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateView as ParsedCreateView;

/**
 * Lowers views from the bindings of the namespace's native view phase.
 * @visibility SqlSemantics
 */
final class SchemaViewLowering
{
    /**
     * Publishes the checked views in their relative written order.
     * @return list<CreateView>
     */
    public function lower(BoundStatement $operation, CreateSchema $schema, ?Identifier $resolved): array
    {
        $views = [];
        $lowering = new CreateViewLowering();
        foreach ($schema->elements as $element) {
            if ($element instanceof ParsedCreateView) {
                Check::input($resolved !== null && !$schema->ifNotExists, 'Schema views require an unguarded, statically named namespace.');
                $views[] = $lowering->lower($operation, $element, $resolved);
            }
        }

        return $views;
    }
}
