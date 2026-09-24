<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\TypeSystem;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Ast\TypeReader;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\Catalog\ObjectAddresses;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\TypeSystem\Domain;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Domain as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\Schema\TableDefinition;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Binds CREATE DOMAIN and the ALTER DOMAIN forms that change defaults, nullability, and constraints.
 * @visibility SqlSemantics
 */
final class Domains
{
    /**
     * Reads the base type and the column qualifiers PostgreSQL accepts for a domain.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function create(Origin $origin, Node $source, QueryContext $context): Statement\CreateDomainStatement
    {
        $name = ObjectAddresses::name(Tree::child($source, ['any_name']) ?? throw new UnclassifiedSql('A domain requires its name.'), $context, 2);
        $type = (new TypeReader(Dialect::PostgreSql))->read(Tree::child($source, ['Typename']) ?? throw new UnclassifiedSql('A domain requires its base type.'));
        $default = null;
        $collation = null;
        $constraints = [];
        foreach (Tree::outer(Tree::child($source, ['ColQualList']) ?? $source, ['ColConstraint']) as $clause) {
            $element = Tree::child($clause, ['ColConstraintElem']);
            $collate = Tree::child($clause, ['any_name']);
            if ($element === null && $collate !== null && $collation === null) {
                $collation = ObjectAddresses::name($collate, $context, 2);
                continue;
            }
            if ($element === null || (self::keyword($element) === 'DEFAULT' && $default !== null)) {
                throw new InvalidSql(InputViolation::DomainConstraint, $clause);
            }
            if (self::keyword($element) === 'DEFAULT') {
                $default = self::expression($element, $context);
                continue;
            }
            $constraints[] = self::constraint($element, self::constraintName($clause, $context), $name, $type, $context);
        }
        $nullability = array_unique(array_map(static fn (Domain\DomainConstraint $constraint): string => $constraint::class, array_filter($constraints, static fn (Domain\DomainConstraint $constraint): bool => !$constraint instanceof Domain\DomainCheck)));
        if (count($nullability) > 1) {
            throw new InvalidSql(InputViolation::DomainConstraint, $source);
        }
        return new Statement\CreateDomainStatement($origin, $name, $type, $default, $collation, $constraints);
    }

    /**
     * Distinguishes the ALTER DOMAIN forms by their keywords after the domain name.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function alter(Origin $origin, Node $source, QueryContext $context): BoundStatement
    {
        $target = Tree::child($source, ['any_name']) ?? throw new UnclassifiedSql('ALTER DOMAIN requires its domain.');
        $domain = ObjectAddresses::name($target, $context, 2);
        $default = Tree::child($source, ['alter_column_default']);
        if ($default !== null) {
            return self::keyword($default) === 'SET' ? new Statement\SetDomainDefaultStatement($origin, $domain, self::expression($default, $context)) : new Statement\DropDomainDefaultStatement($origin, $domain);
        }
        $constraint = Tree::child($source, ['DomainConstraint']);
        if ($constraint !== null) {
            return self::add($origin, $domain, $constraint, $context);
        }
        $words = ObjectAddresses::words($source);
        $tail = array_slice($words, count(ObjectAddresses::words($target)) + 2);
        $names = Tree::outer($source, ['name']);
        $constraintName = static fn (): string => $context->tables->identifiers->name(($names[0] ?? throw new UnclassifiedSql('The domain constraint form requires its name.'))->tokens()[0]);
        return match ($tail[0] ?? '') {
            'SET' => new Statement\AlterDomainNullabilityStatement($origin, $domain, true),
            'VALIDATE' => new Statement\ValidateDomainConstraintStatement($origin, $domain, $constraintName()),
            'DROP' => ($tail[1] ?? '') === 'NOT'
                ? new Statement\AlterDomainNullabilityStatement($origin, $domain, false)
                : new Statement\DropDomainConstraintStatement($origin, $domain, $constraintName(), ($tail[2] ?? '') === 'IF', self::behavior($source)),
            default => throw new UnclassifiedSql('Unclassified ALTER DOMAIN form.'),
        };
    }

    /**
     * NOT VALID applies to CHECK only; NO INHERIT is accepted and ignored; deferrability is rejected.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function add(Origin $origin, QualifiedName $domain, Node $source, QueryContext $context): Statement\AddDomainConstraintStatement
    {
        $element = Tree::child($source, ['DomainConstraintElem']) ?? throw new UnclassifiedSql('A domain constraint requires its definition.');
        $attributes = array_map(static fn (Node $attribute): string => implode(' ', ObjectAddresses::words($attribute)), Tree::outer($element, ['ConstraintAttributeElem']));
        $check = self::keyword($element) === 'CHECK';
        $notValid = in_array('NOT VALID', $attributes, true);
        if (array_intersect($attributes, ['DEFERRABLE', 'INITIALLY DEFERRED']) !== [] || ($notValid && !$check)) {
            throw new InvalidSql(InputViolation::DomainConstraint, $source);
        }
        $name = self::constraintName($source, $context);
        $constraint = $check ? new Domain\DomainCheck(self::check($element, $domain, null, $context), $name) : new Domain\DomainNotNull($name);
        return new Statement\AddDomainConstraintStatement($origin, $domain, $constraint, $notValid);
    }

    /**
     * Only NOT NULL, NULL, and CHECK without NO INHERIT constrain domain values.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function constraint(Node $element, ?string $name, QualifiedName $domain, TypeDescriptor $type, QueryContext $context): Domain\DomainConstraint
    {
        $words = ObjectAddresses::words($element);
        return match (true) {
            $words === ['NOT', 'NULL'] => new Domain\DomainNotNull($name),
            $words === ['NULL'] => new Domain\DomainNullable($name),
            $words[0] === 'CHECK' && Tree::child($element, ['opt_no_inherit']) === null => new Domain\DomainCheck(self::check($element, $domain, $type, $context), $name),
            default => throw new InvalidSql(InputViolation::DomainConstraint, $element),
        };
    }

    /**
     * Binds a CHECK condition against the single column VALUE of the domain's base type.
     * @throws UnclassifiedSql
     */
    public static function check(Node $element, QualifiedName $domain, ?TypeDescriptor $type, QueryContext $context): Expression
    {
        $condition = Tree::child($element, ['a_expr']) ?? throw new UnclassifiedSql('A check constraint requires its condition.');
        $value = new ColumnDefinition('value', $type ?? TypeDescriptor::builtin(Dialect::PostgreSql, 'unknown'), Nullability::MaybeNull, $element);
        $name = $domain->parts[count($domain->parts) - 1];
        $row = new TableDefinition('', $name, [$value], [], $element);
        $scope = new Scope($context->tables->identifiers, [new TableReference('domain', 'domain', $row, new QualifiedName([$name]), null, $element)], queries: $context);
        return (new ExpressionBinder())->bind($condition, $scope);
    }

    /**
     * Binds a default expression, which cannot reference columns.
     * @throws UnclassifiedSql
     */
    public static function expression(Node $source, QueryContext $context): Expression
    {
        $expression = Tree::child($source, ['a_expr', 'b_expr']) ?? throw new UnclassifiedSql('A domain default requires its expression.');
        return (new ExpressionBinder())->bind($expression, new Scope($context->tables->identifiers, queries: $context));
    }

    /**
     * Reads the name of a CONSTRAINT clause, which PostgreSQL ignores on DEFAULT and COLLATE.
     */
    public static function constraintName(Node $clause, QueryContext $context): ?string
    {
        $name = Tree::child($clause, ['name']);
        return $name === null ? null : $context->tables->identifiers->name($name->tokens()[0]);
    }

    /**
     * The first keyword of a clause.
     */
    public static function keyword(Node $clause): string
    {
        $tokens = $clause->tokens();
        return $tokens === [] ? '' : strtoupper($tokens[0]->text);
    }

    /**
     * Reads RESTRICT or CASCADE when the statement spells one.
     */
    public static function behavior(Node $source): DropBehavior
    {
        $behavior = Tree::child($source, ['opt_drop_behavior']);
        return $behavior === null || $behavior->tokens() === [] ? DropBehavior::Default : DropBehavior::from(strtoupper(Tree::text($behavior)));
    }
}
