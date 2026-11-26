<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Truncate;

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
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;
use SqlSemantics\Platform\MySql\Statement\Alter\TruncateTable as ParsedTruncateTable;

/**
 * Resolves the MySQL truncation target and its namespace before publication.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TruncateLowering
{
    /**
     * Publishes the exact table bound by the analyzer.
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
            && $resolution->table->name->catalog === null,
            'TRUNCATE TABLE requires a resolved base table.',
        );
        $name = $resolution->table->name;
        $schema = ($name->schema ?? $context->declarationSchema)->value;
        Check::input($schema !== SessionDatabase::UNNAMED, 'TRUNCATE TABLE needs an absolute table identity.');
        $kind = $resolution->table->temporary ? ExternalRelationKind::TemporaryTable : ExternalRelationKind::BaseTable;
        $target = new TableName(new Identifier($schema), new Identifier($name->name->value));

        $identity = new TableIdentity($target, $kind);

        return new TruncateTable($identity, $this->referrers($context, $identity), $profile->grammar);
    }

    /**
     * Settles the incoming persistent foreign keys of the selected native target.
     * @return list<TableIdentity>
     */
    public function referrers(AnalysisContext $context, TableIdentity $target): array
    {
        if ($target->kind === ExternalRelationKind::TemporaryTable) {
            return [];
        }
        Check::input($context->complete, 'A persistent truncation needs a complete catalog of referencing tables.');
        $database = $target->name->schema->value;
        $table = $target->name->table->value;
        $children = [];
        foreach ($context->foreignKeys as $edge) {
            $parentSchema = $edge->parent->schema?->value;
            if ($edge->parent->name->value !== $table || ($parentSchema !== $database && $parentSchema !== SessionDatabase::UNNAMED)) {
                continue;
            }
            $childSchema = $edge->child->schema?->value;
            Check::input(
                $parentSchema !== SessionDatabase::UNNAMED && $childSchema !== null && $childSchema !== SessionDatabase::UNNAMED,
                'The foreign-key dependency needs an absolute child and parent namespace.'
            );
            $name = new TableName(new Identifier($childSchema), new Identifier($edge->child->name->value));
            $children[] = new TableIdentity($name, ExternalRelationKind::BaseTable);
        }

        return $children;
    }
}
