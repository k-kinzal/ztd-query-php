<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\CreateSequence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\CreateSequenceLowering;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Facade\SchemaMemberScope;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Schema\CreateSchema;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\CreateSequence as ParsedCreateSequence;

/**
 * Resolves sequence declarations in the first phase of schema creation.
 *
 * @visibility SqlSemantics
 */
final class SchemaSequenceLowering
{
    /**
     * Binds sequence members in their relative written order.
     * @return list<CreateSequence>
     */
    public function lower(AnalysisContext $context, CreateSchema $schema, ?Identifier $resolved): array
    {
        $members = [];
        $scope = new SchemaMemberScope();
        $lowering = new CreateSequenceLowering();
        foreach ($schema->elements as $element) {
            if (!$element instanceof ParsedCreateSequence) {
                continue;
            }
            Check::input($resolved !== null && !$schema->ifNotExists, 'Schema tables and sequences require an unguarded, statically named namespace.');
            $inner = $scope->bind($context, new Name($resolved->value), []);
            $members[] = $lowering->lower(new BoundStatement($inner, $element), $element);
        }

        return $members;
    }
}
