<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index\Visibility;

use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexPolicyLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexDeclaration;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\PhysicalIndexKind;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\IndexVisibility;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption;

/**
 * Resolves optimizer visibility requests in the exact physical owner namespace.
 * @visibility SqlSemantics
 */
final class IndexVisibilityLowering
{
    /**
     * Selects ALTER TABLE groups made of native visibility requests and policy clauses.
     * @return non-empty-list<IndexVisibility>|null
     */
    public function edits(AlterTable $statement): ?array
    {
        $edits = [];
        foreach ($statement->commands as $command) {
            if ($command instanceof AlgorithmOption || $command instanceof LockOption) {
                continue;
            }
            if (!$command instanceof IndexVisibility) {
                return null;
            }
            $edits[] = $command;
        }

        return $edits === [] || $statement->ignore ? null : $edits;
    }

    /**
     * Resolves and publishes each ordered secondary index assignment.
     */
    public function lower(BoundStatement $operation, AlterTable $statement): SetIndexVisibility
    {
        $commands = $this->edits($statement);
        Check::input($commands !== null, 'ALTER TABLE requires a visibility change group.');
        $context = $operation->context;
        $profile = $context->profile;
        Check::input(
            $profile->parameters === ParameterStyle::Native && $profile->lexical->equals(new LexicalSettings())
                && $context->relationNames === Comparison::Sensitive,
            'Native index visibility requires the default InnoDB profile.'
        );
        $bound = $operation->facts->relation($statement)->table;
        Check::input(
            $bound instanceof DeclaredTable && $bound->table->kind === RelationKind::BaseTable
                && $bound->table->name->catalog === null && $bound->table->indexes !== null
                && $bound->table->storageEngine !== null && strcasecmp($bound->table->storageEngine, 'InnoDB') === 0,
            'Native index visibility requires a resolved InnoDB table and settled physical indexes.'
        );
        $table = $bound->table;
        $schema = ($table->name->schema ?? $context->declarationSchema)->value;
        Check::input($schema !== SessionDatabase::UNNAMED, 'Native index visibility needs an absolute owning database.');
        $name = new TableName(new Identifier($schema), new Identifier($table->name->name->value));
        $owner = new TableIdentity($name, $table->temporary ? ExternalRelationKind::TemporaryTable : ExternalRelationKind::BaseTable);
        $edits = [];
        foreach ($commands as $command) {
            $edits[] = $this->edit($command, $bound->table->indexes);
        }

        return new SetIndexVisibility($owner, $edits, $profile->grammar, (new IndexPolicyLowering())->alteration($statement));
    }

    /**
     * Binds the selected physical key using the stock native index name rule.
     * @param list<IndexDeclaration> $indexes Settled original physical keys
     */
    public function edit(IndexVisibility $request, array $indexes): IndexVisibilityEdit
    {
        $case = new NativeIdentifierCase();
        $name = $case->fold($request->index->value);
        $matches = array_values(array_filter($indexes, static fn (IndexDeclaration $key): bool => $case->fold($key->name->value) === $name));
        Check::input(count($matches) === 1, 'Native index visibility requires one resolved physical key.');
        $native = $matches[0];
        $kind = match ($native->kind) {
            PhysicalIndexKind::Index => SecondaryIndexKind::Ordinary,
            PhysicalIndexKind::Unique => $native->clusteredUnique ? null : SecondaryIndexKind::Unique,
            PhysicalIndexKind::FullText => SecondaryIndexKind::FullText,
            PhysicalIndexKind::Spatial => SecondaryIndexKind::Spatial,
            PhysicalIndexKind::Primary => null,
        };
        Check::input($kind !== null, 'Native index visibility requires a secondary key.');

        return new IndexVisibilityEdit(new Identifier($native->name->value), $kind, $request->visible);
    }
}
