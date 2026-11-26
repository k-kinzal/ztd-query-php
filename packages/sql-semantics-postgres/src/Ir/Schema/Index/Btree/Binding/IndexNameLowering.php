<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\Binding;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CreateTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IncludedColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexKey;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexPresence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexNames;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\Index\IndexCreationVisibility;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\IndexHeading;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;

/**
 * Resolves the exact relation name in the selected index namespace.
 *
 * @visibility SqlSemantics
 */
final class IndexNameLowering
{
    /**
     * Resolves a written name or allocates an available native automatic name.
     * @param non-empty-list<IndexKey> $keys
     * @param list<IncludedColumn> $included
     * @param array<array-key, CreateTable|TableName|true>|null $memberOccupants
     * @return array{Identifier, IndexPresence}
     */
    public function lower(
        AnalysisContext $context,
        IndexHeading $heading,
        ?Name $written,
        array $keys,
        array $included,
        ?array $memberOccupants,
    ): array {
        $schema = new Name($heading->target->schema->value);
        $visibility = new IndexCreationVisibility();
        $closed = $memberOccupants !== null || ($context->complete && !$context->indexNamespace->hasUnknownNames($schema));
        $occupied = static fn (Name $name): bool => $memberOccupants === null
            ? $visibility->occupied($context, $context->indexNamespace->indexes, $name, $schema)
            : isset($memberOccupants[$name->value]);

        if ($written !== null) {
            $taken = $occupied($written);
            Check::input($taken || $closed, 'CREATE INDEX cannot infer name availability in an open index namespace.');

            return [new Identifier($written->value), $taken ? IndexPresence::Occupied : IndexPresence::Vacant];
        }

        Check::input($closed, 'CREATE INDEX cannot allocate an automatic name in an open index namespace.');
        $attributes = [];
        foreach ([...$keys, ...$included] as $occurrence) {
            $attributes[] = $heading->columns[$occurrence->slot]->value;
        }
        $namePart = implode('_', IndexNames::attributes($attributes));
        $table = $heading->target->table->value;
        $pass = 0;
        do {
            $suffix = $pass === 0 ? 'idx' : 'idx' . $pass;
            $name = new Name(IndexNames::candidate($table, $namePart, $suffix));
            ++$pass;
        } while ($occupied($name));

        return [new Identifier($name->value), IndexPresence::Vacant];
    }
}
