<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Like;

use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTableLike as ParsedCreateTableLike;

/**
 * Resolves the source of a native definition copy and fixes the declaration location.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CreateTableLikeLowering
{
    /**
     * Publishes the bound source and the absolute destination in its chosen namespace.
     */
    public function lower(BoundStatement $operation, ParsedCreateTableLike $statement): CreateTableLike
    {
        $context = $operation->context;
        $profile = $context->profile;
        Check::input($profile->parameters === ParameterStyle::Native && $profile->lexical->equals(new LexicalSettings())
            && $context->relationNames === Comparison::Sensitive, 'CREATE TABLE LIKE requires the default native profile.');
        $resolution = $operation->facts->relation($statement)->table;
        Check::input($resolution instanceof DeclaredTable && $resolution->table->kind === RelationKind::BaseTable
            && $resolution->table->name->catalog === null, 'CREATE TABLE LIKE requires a resolved source table.');
        $original = $resolution->table->name;
        $sourceSchema = ($original->schema ?? $context->declarationSchema)->value;
        $destinationSchema = ($statement->name->schema ?? $context->declarationSchema)->value;
        Check::input(
            $sourceSchema !== SessionDatabase::UNNAMED && $destinationSchema !== SessionDatabase::UNNAMED,
            'CREATE TABLE LIKE requires absolute source and destination names.'
        );
        $sourceKind = $resolution->table->temporary ? ExternalRelationKind::TemporaryTable : ExternalRelationKind::BaseTable;
        $destinationKind = $statement->temporaryWords > 0 ? ExternalRelationKind::TemporaryTable : ExternalRelationKind::BaseTable;
        $source = new TableName(new Identifier($sourceSchema), new Identifier($original->name->value));
        $destination = new TableName(new Identifier($destinationSchema), new Identifier($statement->name->name->value));

        return new CreateTableLike(
            new TableIdentity($destination, $destinationKind),
            new TableIdentity($source, $sourceKind),
            $statement->ifNotExists,
            $profile->grammar
        );
    }
}
