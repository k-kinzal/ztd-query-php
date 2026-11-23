<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema;

use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\From\TextCollation;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableDeclaration;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable as ParsedCreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Option\CharsetOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\CollationOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\EngineOption;

/**
 * Resolves a native CREATE TABLE declaration and its namespace.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CreateTableLowering
{
    /**
     * Resolves the declaration location and the ordered column definitions.
     *
     * @throws InvalidConstruction When the statement has no complete native declaration role
     */
    public function lower(BoundStatement $operation, ParsedCreateTable $statement): CreateTable
    {
        $context = $operation->context;
        $profile = $context->profile;
        Check::input(
            $profile->parameters === ParameterStyle::Native && $profile->lexical->equals(new LexicalSettings())
                && $context->relationNames === Comparison::Sensitive,
            'CREATE TABLE requires the default native profile.'
        );
        Check::input(
            $statement->query === null && $statement->partitioning === null && !$statement->enclosed,
            'CREATE TABLE requires an ordinary table with explicit columns.'
        );
        $engine = null;
        $textOptions = false;
        foreach ($statement->options as $option) {
            if ($option instanceof EngineOption) {
                Check::input($engine === null && strcasecmp($option->engine->value, 'InnoDB') === 0, 'CREATE TABLE requires the native InnoDB storage role.');
                $engine = $option;
            } else {
                Check::input($option instanceof CharsetOption || $option instanceof CollationOption, 'CREATE TABLE requires the native InnoDB storage role.');
                $textOptions = true;
            }
        }
        $tableCollation = (new TableDeclaration())->tableCollation($statement, $profile->grammar);
        Check::input(!$textOptions || $tableCollation !== null, 'CREATE TABLE needs the exact stock utf8mb4_bin table collation.');
        $schema = ($statement->name->schema ?? $context->declarationSchema)->value;
        Check::input($schema !== SessionDatabase::UNNAMED, 'CREATE TABLE requires a named declaration schema.');
        $name = new TableName(new Identifier($schema), new Identifier($statement->name->name->value));
        $kind = $statement->temporary() ? ExternalRelationKind::TemporaryTable : ExternalRelationKind::BaseTable;
        [$columns, $key] = (new CreateTableLayoutLowering())->lower($statement, $profile->grammar, $tableCollation);

        return new CreateTable(
            new TableIdentity($name, $kind),
            $columns,
            $statement->ifNotExists,
            $profile->grammar,
            $tableCollation === null ? null : TextCollation::Utf8mb4Bin,
            $key
        );
    }
}
