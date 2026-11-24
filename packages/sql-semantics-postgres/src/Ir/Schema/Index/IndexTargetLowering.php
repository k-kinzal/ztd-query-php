<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexSchemas;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\Index\IndexBinding;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;

/**
 * Discharges one index selector using the fixed relation path.
 *
 * @visibility SqlSemantics
 */
final class IndexTargetLowering
{
    /**
     * Answers the resolved index or typed absence for one target selector.
     */
    public function resolve(AnalysisContext $context, QualifiedName $name, bool $optional): IndexReference|MissingIndex
    {
        $binding = new IndexBinding();
        $schemas = $name->schema === null ? $context->searchPath : [$name->schema];
        foreach ($schemas as $schema) {
            $match = $binding->find($context, $schema, $name);
            if ($match !== null) {
                return new IndexReference(new Identifier($match->schema->value), new Identifier($match->index->value), new Identifier($match->table->value));
            }
            Check::input(
                !$context->indexNamespace->hasUnknownNames($schema)
                && ($context->complete || IndexSchemas::system($schema->value)),
                'The index selector cannot infer absence in an open index namespace.',
            );
        }
        Check::input($optional, 'The index selector requires a declared index.');
        $schema = $name->schema;

        return new MissingIndex(
            new Identifier($name->name->value),
            $schema === null ? null : new Identifier($schema->value),
            $schema !== null && !$binding->known($context, $schema),
        );
    }

}
