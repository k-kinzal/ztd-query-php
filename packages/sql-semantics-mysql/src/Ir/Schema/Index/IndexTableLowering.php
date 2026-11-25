<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index;

use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;
use SqlSemantics\Platform\MySql\Statement\Table\CreateIndex as ParsedCreateIndex;

/**
 * Resolves the exact native table and signed key fields before publication.
 *
 * @visibility SqlSemantics
 */
final class IndexTableLowering
{
    /**
     * Returns the detached table, ordered owned references and prior identity.
     * @return array{TableSource, list<IndexColumn>, bool}
     */
    public function resolve(BoundStatement $operation, ParsedCreateIndex $statement): array
    {
        $context = $operation->context;
        $profile = $context->profile;
        $resolution = $operation->facts->relation($statement)->table;
        Check::input(
            $resolution instanceof DeclaredTable && $resolution->table->kind === RelationKind::BaseTable
                && $resolution->table->name->catalog === null
                && $resolution->table->storageEngine !== null && strcasecmp($resolution->table->storageEngine, 'InnoDB') === 0,
            'CREATE INDEX requires a resolved ordinary InnoDB declaration.'
        );
        $table = $resolution->table;
        Check::input(count($table->columns) <= 1017, 'An InnoDB index owner can declare at most 1017 fields.');
        $schema = ($table->name->schema ?? $context->declarationSchema)->value;
        Check::input($schema !== SessionDatabase::UNNAMED, 'CREATE INDEX requires an absolute owning table.');
        $kind = $table->temporary ? ExternalRelationKind::TemporaryTable : ExternalRelationKind::BaseTable;
        $legacy = $profile->grammar === GrammarRelease::MySql5651 || $profile->grammar === GrammarRelease::MySql5744;

        [$names, $types, $parts] = (new IndexPartsLowering())->resolve($table, $statement->parts, $legacy);
        $target = new TableSource(new Identifier($schema), new Identifier($table->name->name->value), $names, $types, $kind);

        return [$target, $parts, $table->hasIdentityKey];
    }
}
