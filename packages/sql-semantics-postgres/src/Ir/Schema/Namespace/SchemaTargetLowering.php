<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\Index\IndexBinding;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\UnqualifiedName;

/**
 * Verifies the schema scope of a PostgreSQL namespace change.
 *
 * @visibility SqlSemantics
 */
final class SchemaTargetLowering
{
    /**
     * Resolves the exact name independently of the relation search path.
     */
    public function lower(AnalysisContext $context, ObjectReference $reference): Identifier
    {
        Check::input(
            $context->profile->parameters === ParameterStyle::Native && $context->relationNames === Comparison::Sensitive,
            'ALTER SCHEMA requires the native PostgreSQL namespace profile.'
        );
        Check::input($reference instanceof UnqualifiedName, 'ALTER SCHEMA requires an unqualified namespace selector.');
        Check::input((new IndexBinding())->known($context, $reference->name), 'ALTER SCHEMA requires a declared source namespace.');

        return new Identifier($reference->name->value);
    }
}
