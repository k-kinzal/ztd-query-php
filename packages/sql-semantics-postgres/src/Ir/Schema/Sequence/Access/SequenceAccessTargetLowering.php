<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceTargetLowering;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\Index\IndexBinding;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\RelationsTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\SchemaContentsTarget;

/**
 * Resolves the explicit native sequence selection.
 * @visibility SqlSemantics
 */
final class SequenceAccessTargetLowering
{
    /**
     * Binds each explicit sequence or schema identity in written order.
     */
    public function lower(AnalysisContext $context, PrivilegeTarget $target): NamedSequences|SequencesInSchemas
    {
        Check::input(
            ($target instanceof RelationsTarget || $target instanceof SchemaContentsTarget) && $target->object === PrivilegeObjectKind::Sequence,
            'Sequence privileges require an explicit sequence target.'
        );
        if ($target instanceof RelationsTarget) {
            $lowering = new SequenceTargetLowering();
            $sequences = [];
            foreach ($target->relations as $relation) {
                $sequences[] = $lowering->lower($context, $relation->name, false);
            }
            return new NamedSequences($sequences);
        }
        $binding = new IndexBinding();
        $schemas = [];
        foreach ($target->schemas as $schema) {
            Check::input($binding->known($context, $schema), 'Sequence privileges require a declared schema.');
            $schemas[] = new Identifier($schema->value);
        }

        return new SequencesInSchemas($schemas);
    }
}
