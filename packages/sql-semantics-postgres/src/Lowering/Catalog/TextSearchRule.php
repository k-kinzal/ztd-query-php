<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Catalog;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Collation\CreateConversion;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Collation\RefreshCollationVersion;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\TextSearch\AlterTextSearchDictionary;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\TextSearch\DropTextSearchMapping;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\TextSearch\MappingChange;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\TextSearch\MapTextSearchTokens;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\TextSearch\ReplaceTextSearchDictionary;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the text search, collation and conversion commands.
 *
 * Rule: PG-TEXT-SEARCH-LOWER-001. Scope: `AlterTSDictionaryStmt`,
 * `AlterTSConfigurationStmt`, `any_with`, `AlterCollationStmt`,
 * `CreateConversionStmt`. Constructors: `AlterTextSearchDictionary`,
 * `MapTextSearchTokens`, `ReplaceTextSearchDictionary`,
 * `DropTextSearchMapping`, `RefreshCollationVersion`, `CreateConversion`.
 * `WITH_LA` is the WITH keyword read with lookahead; both are the mandatory
 * WITH of the mapping. Source: https://www.postgresql.org/docs/17/sql-altertsdictionary.html,
 * https://www.postgresql.org/docs/17/sql-altertsconfig.html, https://www.postgresql.org/docs/17/sql-altercollation.html,
 * https://www.postgresql.org/docs/17/sql-createconversion.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class TextSearchRule
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a text search, collation or conversion command.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);
        $names = $this->lowering->names;
        if ($statement->name === 'AlterTSConfigurationStmt') {
            $this->with($form);
        }

        return match ($form->signature) {
            'AlterTSDictionaryStmt: ALTER TEXT_P SEARCH DICTIONARY any_name definition' => new AlterTextSearchDictionary($names->dotted($form->node(4)), $this->lowering->options->definitions($form->node(5))),
            'AlterTSConfigurationStmt: ALTER TEXT_P SEARCH CONFIGURATION any_name ADD_P MAPPING FOR name_list any_with any_name_list' => new MapTextSearchTokens($names->dotted($form->node(4)), MappingChange::Add, $names->names($form->node(8)), $names->dottedList($form->node(10))),
            'AlterTSConfigurationStmt: ALTER TEXT_P SEARCH CONFIGURATION any_name ALTER MAPPING FOR name_list any_with any_name_list' => new MapTextSearchTokens($names->dotted($form->node(4)), MappingChange::Alter, $names->names($form->node(8)), $names->dottedList($form->node(10))),
            'AlterTSConfigurationStmt: ALTER TEXT_P SEARCH CONFIGURATION any_name ALTER MAPPING REPLACE any_name any_with any_name' => new ReplaceTextSearchDictionary($names->dotted($form->node(4)), [], $names->dotted($form->node(8)), $names->dotted($form->node(10))),
            'AlterTSConfigurationStmt: ALTER TEXT_P SEARCH CONFIGURATION any_name ALTER MAPPING FOR name_list REPLACE any_name any_with any_name' => new ReplaceTextSearchDictionary($names->dotted($form->node(4)), $names->names($form->node(8)), $names->dotted($form->node(10)), $names->dotted($form->node(12))),
            'AlterTSConfigurationStmt: ALTER TEXT_P SEARCH CONFIGURATION any_name DROP MAPPING FOR name_list' => new DropTextSearchMapping($names->dotted($form->node(4)), false, $names->names($form->node(8))),
            'AlterTSConfigurationStmt: ALTER TEXT_P SEARCH CONFIGURATION any_name DROP MAPPING IF_P EXISTS FOR name_list' => new DropTextSearchMapping($names->dotted($form->node(4)), true, $names->names($form->node(10))),
            'AlterCollationStmt: ALTER COLLATION any_name REFRESH VERSION_P' => new RefreshCollationVersion($names->dotted($form->node(2))),
            'CreateConversionStmt: CREATE opt_default CONVERSION_P any_name FOR Sconst TO Sconst FROM any_name' => new CreateConversion(
                $this->lowering->flags->present($form->node(1)),
                $names->dotted($form->node(3)),
                $this->lowering->literals->string($form->node(5)),
                $this->lowering->literals->string($form->node(7)),
                $names->dotted($form->node(9)),
            ),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Accepts the `any_with` of a configuration change, which is the mandatory WITH in either lexical form.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function with(\SqlSemantics\Lowering\Form $form): void
    {
        foreach ($form->node->children as $child) {
            if ($child instanceof Node && $child->name === 'any_with') {
                $with = $this->lowering->productions->form($child);
                if ($with->signature !== 'any_with: WITH' && $with->signature !== 'any_with: WITH_LA') {
                    throw ImplementationGap::production($with);
                }
            }
        }
    }
}
