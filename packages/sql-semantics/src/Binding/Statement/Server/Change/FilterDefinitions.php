<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Server\Change;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\MySqlNames;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\Server\Literals;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Replication\Filter;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Reads one rule of CHANGE REPLICATION FILTER.
 * @visibility SqlSemantics
 */
final class FilterDefinitions
{
    /**
     * Reads the rule keyword and its value list.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function read(Node $definition, Identifiers $identifiers): Filter\ReplicationFilter
    {
        $rule = Filter\FilterRule::tryFrom(strtoupper($definition->tokens()[0]->text ?? '')) ?? throw new UnclassifiedSql('Unclassified replication filter: ' . $definition->toString());
        $names = array_map(static fn (Node $name): string => MySqlNames::read($name->tokens()[0], $identifiers), Tree::outer($definition, ['filter_db_ident']));
        try {
            return match ($rule) {
                Filter\FilterRule::DoDatabase, Filter\FilterRule::IgnoreDatabase => new Filter\DatabaseFilter($rule, $names),
                Filter\FilterRule::DoTable, Filter\FilterRule::IgnoreTable => new Filter\TableFilter($rule, array_map(static fn (Node $table): QualifiedName => self::table($table, $identifiers), Tree::outer($definition, ['filter_table_ident']))),
                Filter\FilterRule::WildDoTable, Filter\FilterRule::WildIgnoreTable => new Filter\WildTableFilter($rule, array_map(Literals::text(...), Tree::outer($definition, ['filter_wild_db_table_string']))),
                Filter\FilterRule::RewriteDatabase => new Filter\RewriteFilter(array_map(static fn (array $pair): Filter\DatabaseRewrite => new Filter\DatabaseRewrite($pair[0], $pair[1] ?? $pair[0]), array_chunk($names, 2))),
            };
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::ReplicationFilter, $definition, $error);
        }
    }

    /**
     * Reads database.table as a two-part name.
     * @throws InvalidStructure
     */
    public static function table(Node $table, Identifiers $identifiers): QualifiedName
    {
        $parts = [];
        foreach ($table->tokens() as $token) {
            if ($token->text !== '.') {
                $parts[] = MySqlNames::read($token, $identifiers);
            }
        }
        return new QualifiedName($parts);
    }
}
