<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add;

use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\References;

/**
 * Binds both sides of a native InnoDB addition through their distinct namespaces.
 * @visibility SqlSemantics
 */
final class ForeignKeyTables
{
    /**
     * Resolves the actual statement owner, including temporary shadowing.
     * @return array{Table, TableIdentity}
     */
    public function child(BoundStatement $operation, AlterTable $statement): array
    {
        $context = $operation->context;
        Check::input(
            $context->complete && $context->profile->parameters === ParameterStyle::Native
                && $context->profile->lexical->equals(new LexicalSettings())
                && $context->relationNames === Comparison::Sensitive,
            'Native foreign-key addition needs a complete catalog in the default InnoDB profile.'
        );
        $binding = $operation->facts->relation($statement)->table;
        Check::input($binding instanceof DeclaredTable, 'Native foreign-key addition needs a resolved owning table.');

        return [$binding->table, $this->identity($binding->table, $context)];
    }

    /**
     * Resolves the referenced name in the persistent dictionary of the child database.
     * @return array{Table, TableIdentity}
     */
    public function parent(BoundStatement $operation, References $written): array
    {
        $binding = $operation->facts->relation($written)->table;
        Check::input($binding instanceof DeclaredTable && !$binding->table->temporary, 'A native foreign key needs one resolved persistent parent table.');

        return [$binding->table, $this->identity($binding->table, $operation->context)];
    }

    /**
     * Checks a persistent InnoDB coordinate and complete native table facts.
     */
    public function identity(Table $table, AnalysisContext $context): TableIdentity
    {
        $schema = ($table->name->schema ?? $context->declarationSchema)->value;
        Check::input(
            $table->complete && !$table->temporary && $table->kind === RelationKind::BaseTable && $table->name->catalog === null
                && $table->storageEngine !== null && strcasecmp($table->storageEngine, 'InnoDB') === 0 && $table->indexes !== null
                && $schema !== SessionDatabase::UNNAMED,
            'Native foreign-key addition requires an absolute persistent InnoDB table with known physical keys.'
        );

        return new TableIdentity(
            new TableName(new Identifier($schema), new Identifier($table->name->name->value)),
            ExternalRelationKind::BaseTable,
        );
    }
}
