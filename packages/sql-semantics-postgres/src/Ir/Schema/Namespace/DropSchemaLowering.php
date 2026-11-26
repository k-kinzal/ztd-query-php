<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Facade\Index\IndexBinding;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Drop;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\UnqualifiedName;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;

/**
 * Resolves DROP SCHEMA targets before publication.
 *
 * @visibility SqlSemantics
 */
final class DropSchemaLowering
{
    /**
     * Binds every written namespace without using relation search order.
     */
    public function lower(BoundStatement $operation, Drop $statement): DropSchema
    {
        $context = $operation->context;
        Check::input($statement->kind === ObjectKind::Schema && $context->profile->parameters === ParameterStyle::Native
            && $context->relationNames === Comparison::Sensitive, 'DROP SCHEMA requires the native PostgreSQL namespace profile.');
        $binding = new IndexBinding();
        $targets = [];
        foreach ($statement->objects as $object) {
            Check::input($object instanceof UnqualifiedName, 'DROP SCHEMA requires an unqualified namespace selector.');
            $present = $binding->known($context, $object->name);
            Check::input($present || $context->complete, 'An absent schema requires a complete namespace declaration context.');
            $targets[] = new SchemaDropTarget(new Identifier($object->name->value), $present);
        }

        return new DropSchema($targets, $statement->ifExists, $statement->behavior === DropBehavior::Cascade, $context->profile->grammar);
    }
}
