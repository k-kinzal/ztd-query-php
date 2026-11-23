<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\View;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Resolution\TableLookup;
use SqlSemantics\Platform\MySql\Model\Resolution\TableNamespace;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\MissingTable;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;
use SqlSemantics\Platform\MySql\Statement\View\DropView as ParsedDropView;

/**
 * Resolves every view identity before publishing a complete DROP VIEW.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DropViewLowering
{
    /**
     * Binds requested view names under the fixed native profile.
     * @throws InvalidConstruction When the view identity or the native profile cannot be resolved
     */
    public function lower(BoundStatement $operation, ParsedDropView $statement): DropView
    {
        $context = $operation->context;
        $profile = $context->profile;
        Check::input($profile->parameters === ParameterStyle::Native && $profile->lexical->equals(new LexicalSettings())
            && $context->relationNames === Comparison::Sensitive, 'DROP VIEW requires the default native profile.');
        $schemas = [$context->declarationSchema->value => true];
        foreach ($context->tables as $table) {
            $schemas[($table->name->schema ?? $context->declarationSchema)->value] = true;
        }
        $lookup = new TableLookup();
        $targets = [];
        foreach ($statement->views as $target) {
            $resolution = $lookup->find($context, $target, TableNamespace::Persistent);
            if ($resolution instanceof DeclaredTable && $resolution->table->kind === RelationKind::View && $resolution->table->complete) {
                $name = $resolution->table->name;
            } elseif ($statement->ifExists && $resolution instanceof MissingTable) {
                $name = $resolution->name;
            } else {
                throw new InvalidConstruction('DROP VIEW needs a declared view or a permitted known absence.');
            }
            $schema = ($name->schema ?? $context->declarationSchema)->value;
            Check::input(
                $name->catalog === null && $schema !== SessionDatabase::UNNAMED && isset($schemas[$schema]),
                'DROP VIEW requires a known absolute view identity.'
            );
            $targets[] = new ViewName(new Identifier($schema), new Identifier($name->name->value));
        }

        return new DropView($targets, $statement->ifExists, $profile->grammar);
    }
}
