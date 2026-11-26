<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Alter;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;
use SqlSemantics\Platform\MySql\Statement\Alter\RenameTable as ParsedRenameTable;

/**
 * Resolves absolute MySQL names and checks their initial bindings.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TableRenameLowering
{
    /**
     * Publishes the complete ordered move sequence.
     */
    public function lower(BoundStatement $operation, ParsedRenameTable $statement): RenameTable
    {
        $context = $operation->context;
        $profile = $context->profile;
        Check::input($profile->parameters === ParameterStyle::Native && $profile->lexical->equals(new LexicalSettings())
            && $context->relationNames === Comparison::Sensitive, 'RENAME TABLE requires the default native profile.');
        $fallback = (new SessionDatabase())->named($context);
        $moves = [];
        foreach ($statement->renamings as $move) {
            $resolution = $operation->facts->relation($move)->table;
            Check::input(
                $resolution instanceof DeclaredTable && $resolution->table->kind === RelationKind::BaseTable
                && !$resolution->table->temporary && $resolution->table->name->catalog === null,
                'RENAME TABLE requires a resolved persistent base-table origin.'
            );
            $initial = $resolution->table->name;
            $originSchema = ($initial->schema ?? $context->declarationSchema)->value;
            $fromSchema = ($move->from->schema ?? $fallback)?->value;
            $toSchema = ($move->to->schema ?? $fallback)?->value;
            Check::input(
                $fromSchema !== null && $toSchema !== null && $originSchema !== SessionDatabase::UNNAMED,
                'RENAME TABLE needs absolute current, origin and destination names.'
            );
            $from = new TableName(new Identifier($fromSchema), new Identifier($move->from->name->value));
            $to = new TableName(new Identifier($toSchema), new Identifier($move->to->name->value));
            $origin = new TableName(new Identifier($originSchema), new Identifier($initial->name->value));
            $moves[] = new TableRename($from, $to, $origin);
        }

        return new RenameTable($moves, $profile->grammar);
    }
}
