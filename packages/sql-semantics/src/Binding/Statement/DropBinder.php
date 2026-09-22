<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds index and trigger deletion forms whose mandatory owners differ by dialect.
 * @visibility SqlSemantics
 */
final class DropBinder
{
    /**
     * Requires the owning table for MySQL indexes and PostgreSQL triggers.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $node, QueryContext $context): ?BoundStatement
    {
        $words = array_map(static fn ($token): string => strtoupper($token->text), $node->tokens());
        if (($words[0] ?? '') !== 'DROP') {
            return null;
        }
        $identifiers = $context->tables->identifiers;
        if ($origin->dialect === Dialect::MySql && ($words[1] ?? '') === 'INDEX') {
            return self::mysqlIndex($origin, $node, $context);
        }
        if ($origin->dialect === Dialect::PostgreSql && ($words[1] ?? '') === 'INDEX' && ($words[2] ?? '') === 'CONCURRENTLY') {
            $names = Tree::outer($node, ['any_name']);
            if (count($names) !== 1 || end($words) === 'CASCADE') {
                throw new InvalidSql(InputViolation::ConcurrentIndexDrop, $node);
            }
            return new Statement\DropIndexConcurrentlyStatement($origin, new QualifiedName($identifiers->parts($names[0])), in_array('IF', $words, true));
        }
        if ($origin->dialect === Dialect::PostgreSql && ($words[1] ?? '') === 'TRIGGER') {
            $name = Tree::child($node, ['name']) ?? throw new UnclassifiedSql('DROP TRIGGER requires its name.');
            $table = Tree::child($node, ['any_name']) ?? throw new UnclassifiedSql('DROP TRIGGER requires its owning table.');
            return new Statement\DropTableTriggerStatement($origin, $identifiers->name($name->tokens()[0]), new QualifiedName($identifiers->parts($table)), in_array('IF', $words, true), Definition\DropBehavior::tryFrom(end($words)) ?? Definition\DropBehavior::Default);
        }
        return null;
    }

    /**
     * Binds the required owning table and MySQL index-rebuild policies.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function mysqlIndex(Origin $origin, Node $node, QueryContext $context): Statement\DropTableIndexStatement
    {
        $identifiers = $context->tables->identifiers;
        $name = Tree::child($node, ['ident']) ?? throw new UnclassifiedSql('DROP INDEX requires an index name.');
        $table = Tree::child($node, ['table_ident']) ?? throw new UnclassifiedSql('DROP INDEX requires a table name.');
        $algorithm = Tree::outer($node, ['alter_algorithm_option_value'])[0] ?? null;
        $lock = Tree::outer($node, ['alter_lock_option_value'])[0] ?? null;
        return new Statement\DropTableIndexStatement($origin, $identifiers->name($name->tokens()[0]), new QualifiedName($identifiers->parts($table)), $algorithm === null ? Definition\IndexAlgorithm::Default : (Definition\IndexAlgorithm::tryFrom(strtoupper(Tree::text($algorithm))) ?? throw new InvalidSql(InputViolation::AlterAlgorithm, $algorithm)), $lock === null ? Definition\IndexLock::Default : (Definition\IndexLock::tryFrom(strtoupper(Tree::text($lock))) ?? throw new InvalidSql(InputViolation::AlterLock, $lock)));
    }
}
