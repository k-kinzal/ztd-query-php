<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Catalog;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Statement\Statement;

/**
 * The entry point of the catalog family: schemas, databases, tablespaces, extensions, foreign data, languages, access methods, publications, subscriptions, text search, collations, conversions, domains, types, casts and transforms.
 *
 * Rule: PG-CATALOG-001. Scope: the statement nonterminals of the family
 * (`php .agent/pg-families.php Catalog`), each handed to the rule of its
 * group, and `opt_enum_val_list`, `enum_val_list`. Termination: each
 * statement is lowered by one rule; lists are flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-commands.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Catalogs
{
    /**
     * The rule group of each statement nonterminal.
     */
    private const GROUPS = [
        'CreateSchemaStmt' => 'schema',
        'CreatedbStmt' => 'database', 'AlterDatabaseStmt' => 'database', 'AlterDatabaseSetStmt' => 'database', 'DropdbStmt' => 'database',
        'CreateTableSpaceStmt' => 'database', 'DropTableSpaceStmt' => 'database', 'AlterTblSpcStmt' => 'database',
        'CreateExtensionStmt' => 'extension', 'AlterExtensionStmt' => 'extension', 'AlterExtensionContentsStmt' => 'extension',
        'CreatePLangStmt' => 'extension', 'CreateAmStmt' => 'extension',
        'CreateFdwStmt' => 'foreign', 'AlterFdwStmt' => 'foreign', 'CreateForeignServerStmt' => 'foreign', 'AlterForeignServerStmt' => 'foreign',
        'CreateUserMappingStmt' => 'foreign', 'AlterUserMappingStmt' => 'foreign', 'DropUserMappingStmt' => 'foreign', 'ImportForeignSchemaStmt' => 'foreign',
        'CreatePublicationStmt' => 'publication', 'AlterPublicationStmt' => 'publication', 'CreateSubscriptionStmt' => 'subscription',
        'AlterSubscriptionStmt' => 'subscription', 'DropSubscriptionStmt' => 'subscription',
        'AlterTSDictionaryStmt' => 'text', 'AlterTSConfigurationStmt' => 'text', 'AlterCollationStmt' => 'text', 'CreateConversionStmt' => 'text',
        'CreateDomainStmt' => 'domain', 'AlterDomainStmt' => 'domain',
        'AlterEnumStmt' => 'type', 'AlterCompositeTypeStmt' => 'type', 'AlterTypeStmt' => 'type',
        'CreateCastStmt' => 'cast', 'DropCastStmt' => 'cast', 'CreateTransformStmt' => 'cast', 'DropTransformStmt' => 'cast',
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a statement of the family, such as `CreateSchemaStmt`, `CreatedbStmt`, `CreateExtensionStmt` or `AlterDomainStmt`.
     *
     * @throws ImplementationGap When the nonterminal is not a statement of the family
     */
    public function statement(Node $statement): Statement
    {
        $group = self::GROUPS[$statement->name] ?? throw ImplementationGap::production($this->lowering->productions->form($statement));

        return match ($group) {
            'schema' => (new SchemaRule($this->lowering))->statement($statement),
            'database' => (new DatabaseRule($this->lowering))->statement($statement),
            'extension' => (new ExtensionRule($this->lowering))->statement($statement),
            'foreign' => (new ForeignDataRule($this->lowering))->statement($statement),
            'publication' => (new PublicationRule($this->lowering))->statement($statement),
            'subscription' => (new SubscriptionRule($this->lowering))->statement($statement),
            'text' => (new TextSearchRule($this->lowering))->statement($statement),
            'domain' => (new DomainRule($this->lowering))->statement($statement),
            'type' => (new TypeRule($this->lowering))->statement($statement),
            'cast' => (new CastRule($this->lowering))->statement($statement),
        };
    }

    /**
     * Lowers `opt_enum_val_list`: the labels of an enum type in order; no label is an empty list.
     *
     * @return list<StringConstant>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function enumValues(Node $labels): array
    {
        $form = $this->lowering->productions->form($labels);
        if ($form->signature === 'opt_enum_val_list:') {
            return [];
        }
        if ($form->signature !== 'opt_enum_val_list: enum_val_list') {
            throw ImplementationGap::production($form);
        }
        $values = [];
        foreach ($this->lowering->items($form->node(0), 'enum_val_list: Sconst', 'enum_val_list: enum_val_list , Sconst') as $label) {
            $values[] = $this->lowering->literals->string($label);
        }

        return $values;
    }
}
