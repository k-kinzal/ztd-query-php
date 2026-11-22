<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;
use SqlSemantics\Platform\MySql\Statement\Alter\AbsentTable;
use SqlSemantics\Platform\MySql\Statement\Alter\DropTable as ParsedDropTable;

/**
 * Resolves the absolute identities of tables before publishing their removal.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DropTableLowering
{
    /**
     * Publishes a persistent table removal with all names resolved.
     *
     * @throws InvalidConstruction When a target cannot be resolved in the supported native profile
     */
    public function lower(BoundStatement $operation, ParsedDropTable $statement): DropTable
    {
        $context = $operation->context;
        $profile = $context->profile;
        Check::input($profile->parameters === ParameterStyle::Native && $profile->lexical->equals(new LexicalSettings())
            && $context->relationNames === Comparison::Sensitive, 'DROP TABLE requires the default native profile.');
        Check::input(!$statement->temporary, 'DROP TABLE requires a persistent table target.');

        $schemas = [$context->declarationSchema->value => true];
        foreach ($context->tables as $table) {
            $schemas[($table->name->schema ?? $context->declarationSchema)->value] = true;
        }
        $targets = [];
        foreach ($statement->tables as $target) {
            $resolution = $operation->facts->relation($target)->table;
            if ($resolution instanceof DeclaredTable && $resolution->table->kind === RelationKind::BaseTable) {
                $name = $resolution->table->name;
                $schema = ($name->schema ?? $context->declarationSchema)->value;
            } elseif ($statement->ifExists && $resolution instanceof AbsentTable) {
                $name = $resolution->name;
                Check::input($name->schema !== null && isset($schemas[$name->schema->value]), 'Optional DROP TABLE needs a known explicit schema.');
                $schema = $name->schema->value;
            } else {
                throw new InvalidConstruction('DROP TABLE requires an ordinary table or a known schema-qualified absence.');
            }
            Check::input($name->catalog === null && $schema !== SessionDatabase::UNNAMED, 'DROP TABLE needs an absolute table identity.');
            $targets[] = new TableName(new Identifier($schema), new Identifier($name->name->value));
        }

        return new DropTable($targets, $statement->ifExists, $profile->grammar);
    }
}
