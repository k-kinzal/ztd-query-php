<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Truncate;

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
use SqlSemantics\Platform\MySql\Statement\Alter\TruncateTable as ParsedTruncateTable;

/**
 * Resolves the persistent MySQL truncation target before publication.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TruncateLowering
{
    /**
     * Publishes the exact persistent relation bound by the analyzer.
     */
    public function lower(BoundStatement $operation, ParsedTruncateTable $statement): TruncateTable
    {
        $context = $operation->context;
        $profile = $context->profile;
        Check::input($profile->parameters === ParameterStyle::Native && $profile->lexical->equals(new LexicalSettings())
            && $context->relationNames === Comparison::Sensitive, 'TRUNCATE TABLE requires the default native profile.');
        $resolution = $operation->facts->relation($statement)->table;
        Check::input(
            $resolution instanceof DeclaredTable && $resolution->table->kind === RelationKind::BaseTable
            && !$resolution->table->temporary && $resolution->table->name->catalog === null,
            'TRUNCATE TABLE requires a resolved persistent base table.',
        );
        $name = $resolution->table->name;
        $schema = ($name->schema ?? $context->declarationSchema)->value;
        Check::input($schema !== SessionDatabase::UNNAMED, 'TRUNCATE TABLE needs an absolute table identity.');
        $target = new TableName(new Identifier($schema), new Identifier($name->name->value));

        return new TruncateTable($target, $profile->grammar);
    }
}
