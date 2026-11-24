<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\Access;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\CreateIndex;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexPresence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\GrantSequenceAccess;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceAccessLowering;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexDeclaration;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexDeclarationKind;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Facade\SchemaMemberScope;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Grant;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Schema\CreateSchema;

/**
 * Lowers grants in the transient namespace produced by the earlier phases.
 *
 * @visibility SqlSemantics
 */
final class SchemaGrantLowering
{
    /**
     * Resolves targets against the checked local members and the outer catalog before publication.
     *
     * @param list<CreateIndex> $indexes
     * @return list<GrantSequenceAccess>
     */
    public function lower(BoundStatement $operation, CreateSchema $schema, ?Identifier $resolved, array $indexes): array
    {
        $grants = [];
        $lowering = new SequenceAccessLowering();
        $context = null;
        foreach ($schema->elements as $element) {
            if (!$element instanceof Grant) {
                continue;
            }
            Check::input($resolved !== null && !$schema->ifNotExists, 'Schema grants require an unguarded, statically named namespace.');
            if ($context === null) {
                $name = new Name($resolved->value);
                $relations = [];
                foreach ($indexes as $index) {
                    if ($index->presence === IndexPresence::Vacant) {
                        $relations[] = new IndexDeclaration(
                            $name,
                            new Name($index->name->value),
                            new Name($index->table->table->value),
                            IndexDeclarationKind::Ordinary
                        );
                    }
                }
                $context = (new SchemaMemberScope())->bind($operation->context, $name, $operation->facts->declarations, $relations);
            }
            $grant = $lowering->lower($context, $element);
            Check::input($grant instanceof GrantSequenceAccess, 'Schema grants require sequence grant operands.');
            $grants[] = $grant;
        }

        return $grants;
    }
}
