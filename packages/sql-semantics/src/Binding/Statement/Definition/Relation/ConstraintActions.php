<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Relation;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\ConstraintReader;
use SqlSemantics\Ast\Definition\IndexKeys;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Configuration\SettingTokens;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Schema\ConstraintBinder;
use SqlSemantics\Binding\Schema\IndexBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\Catalog\ObjectAddresses;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\Relation\Constraint;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Scalar\Value;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Constraint\Check;
use SqlSemantics\Schema\Constraint\CheckingTime;
use SqlSemantics\Schema\Storage\ImpliedSetting;
use SqlSemantics\Schema\Storage\Parameter;

/**
 * Binds the constraint commands of ALTER TABLE, including exclusion constraints.
 * @visibility SqlSemantics
 */
final class ConstraintActions
{
    /**
     * ADD binds keys, foreign keys, and checks through the declaration binder and exclusions natively.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function add(Node $command, Scope $scope, QueryContext $context): Constraint\AddConstraint|Constraint\AddExclusionConstraint|Constraint\AddIndexConstraint
    {
        $node = Tree::child($command, ['TableConstraint']) ?? throw new UnclassifiedSql('ADD CONSTRAINT requires its constraint.');
        $attributes = array_map(static fn (Node $element): string => strtoupper(Tree::text($element)), Tree::outer($node, ['ConstraintAttributeElem']));
        $existing = Tree::outer($node, ['ExistingIndex'])[0] ?? null;
        if ($existing !== null) {
            return self::existing($node, $existing, $attributes, $scope);
        }
        $parsed = (new ConstraintReader($context->tables->identifiers))->read($node);
        if ($parsed === null) {
            if (!in_array('EXCLUDE', ObjectAddresses::words($node), true)) {
                throw new UnclassifiedSql('Unclassified table constraint: ' . Tree::text($node));
            }
            return new Constraint\AddExclusionConstraint(self::exclusion($node, $attributes, $scope, $context));
        }
        $constraint = ConstraintBinder::bind($parsed, $scope);
        $noInherit = in_array('NO INHERIT', $attributes, true);
        if ($noInherit && !$constraint instanceof Check) {
            throw new InvalidSql(InputViolation::ConstraintAttribute, $node);
        }
        if ($constraint instanceof Check) {
            $constraint = new Check($constraint->predicate, !in_array('NOT ENFORCED', $attributes, true), $noInherit, $constraint->name, $constraint->source);
        }
        try {
            return new Constraint\AddConstraint($constraint, in_array('NOT VALID', $attributes, true));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::ConstraintAttribute, $node, $error);
        }
    }

    /**
     * Binds a primary key or unique constraint that adopts an existing index; NOT VALID and NO INHERIT do not apply to it.
     * @param list<string> $attributes
     * @throws InvalidSql
     */
    public static function existing(Node $node, Node $existing, array $attributes, Scope $scope): Constraint\AddIndexConstraint
    {
        if (array_intersect($attributes, ['NOT VALID', 'NO INHERIT']) !== []) {
            throw new InvalidSql(InputViolation::ConstraintAttribute, $node);
        }
        $identifiers = $scope->identifiers;
        $nameNode = Tree::child($node, ['name']);
        $indexTokens = $existing->tokens();
        return new Constraint\AddIndexConstraint(
            strtoupper($indexTokens[0]->text ?? '') === 'PRIMARY' || in_array('PRIMARY', ObjectAddresses::words($node), true) ? \SqlSemantics\Schema\ConstraintKind::PrimaryKey : \SqlSemantics\Schema\ConstraintKind::Unique,
            $identifiers->name($indexTokens[count($indexTokens) - 1]),
            $nameNode === null ? null : $identifiers->name($nameNode->tokens()[0]),
            self::checking($attributes, $node),
        );
    }

    /**
     * Reads the indexed elements, operators, storage, and predicate of an exclusion constraint.
     * @param list<string> $attributes
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function exclusion(Node $node, array $attributes, Scope $scope, QueryContext $context): Constraint\ExclusionConstraint
    {
        $identifiers = $context->tables->identifiers;
        $elements = [];
        foreach (Tree::outer($node, ['ExclusionConstraintElem']) as $element) {
            $key = IndexBinder::element(IndexKeys::element(Tree::child($element, ['index_elem']) ?? throw new UnclassifiedSql('An exclusion element requires its key.'), $identifiers), $scope);
            $elements[] = new Constraint\ExclusionElement($key, ObjectAddresses::name(Tree::child($element, ['any_operator']) ?? throw new UnclassifiedSql('An exclusion element requires its operator.'), $context, 2));
        }
        $nameNode = Tree::child($node, ['name']);
        $body = Tree::child($node, ['ConstraintElem']) ?? $node;
        $method = Tree::child($body, ['access_method_clause']);
        $include = Tree::child($body, ['opt_c_include']);
        $tablespace = Tree::child($body, ['OptConsTableSpace']);
        $where = Tree::child($body, ['OptWhereClause']);
        $predicate = $where === null || !Tree::hasTokens($where) ? null : (new ExpressionBinder())->bind(Tree::child($where, ['a_expr']) ?? throw new UnclassifiedSql('WHERE requires its predicate.'), $scope);
        return new Constraint\ExclusionConstraint(
            Collections::nonEmpty($elements),
            $nameNode === null ? null : $identifiers->name($nameNode->tokens()[0]),
            $method === null || !Tree::hasTokens($method) ? null : $identifiers->name($method->tokens()[count($method->tokens()) - 1]),
            $include === null ? [] : array_map(static fn (Node $column): string => $identifiers->name($column->tokens()[0]), Tree::outer($include, ['columnElem'])),
            array_map(static fn (Node $element): Parameter => self::definition($element, $scope), Tree::outer($node, ['def_elem'])),
            $tablespace === null || !Tree::hasTokens($tablespace) ? null : $identifiers->name($tablespace->tokens()[count($tablespace->tokens()) - 1]),
            $predicate,
            self::checking($attributes, $node),
        );
    }

    /**
     * A definition element names an index storage parameter with an optional value.
     * @throws UnclassifiedSql
     */
    public static function definition(Node $element, Scope $scope): Parameter
    {
        $tokens = $element->tokens();
        $equals = array_search('=', array_map(static fn ($token): string => $token->text, $tokens), true);
        $name = new QualifiedName([$scope->identifiers->name($tokens[0])]);
        if ($equals === false) {
            return new Parameter($name, ImpliedSetting::Enabled);
        }
        $valueTokens = array_slice($tokens, $equals + 1);
        $value = $valueTokens === [] ? throw new UnclassifiedSql('A definition requires its value.') : SettingTokens::value($valueTokens, $element, $scope);
        if (!$value instanceof Value\Literal && !$value instanceof Value\ConfigurationIdentifier && !$value instanceof Value\ConfigurationKeyword) {
            throw new UnclassifiedSql('Unclassified definition value.');
        }
        return new Parameter($name, $value);
    }

    /**
     * Combines DEFERRABLE and INITIALLY attributes into one checking time.
     * @param list<string> $attributes
     * @throws InvalidSql
     */
    public static function checking(array $attributes, Node $source): CheckingTime
    {
        $deferrable = in_array('DEFERRABLE', $attributes, true);
        $notDeferrable = in_array('NOT DEFERRABLE', $attributes, true);
        $deferred = in_array('INITIALLY DEFERRED', $attributes, true);
        if (($deferrable && $notDeferrable) || ($notDeferrable && $deferred)) {
            throw new InvalidSql(InputViolation::ConstraintAttribute, $source);
        }
        if ($deferred) {
            return CheckingTime::DeferrableDeferred;
        }
        return $deferrable ? CheckingTime::DeferrableImmediate : CheckingTime::Immediate;
    }

    /**
     * ALTER CONSTRAINT changes only the checking time of a foreign key.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function alter(Node $command, QueryContext $context): Constraint\AlterConstraint
    {
        $name = Tree::child($command, ['name']) ?? throw new UnclassifiedSql('ALTER CONSTRAINT requires its name.');
        $attributes = array_map(static fn (Node $element): string => strtoupper(Tree::text($element)), Tree::outer($command, ['ConstraintAttributeElem']));
        if (array_diff($attributes, ['DEFERRABLE', 'NOT DEFERRABLE', 'INITIALLY DEFERRED', 'INITIALLY IMMEDIATE']) !== []) {
            throw new InvalidSql(InputViolation::ConstraintAttribute, $command);
        }
        return new Constraint\AlterConstraint($context->tables->identifiers->name($name->tokens()[0]), self::checking($attributes, $command));
    }

    /**
     * DROP CONSTRAINT [IF EXISTS] name [CASCADE | RESTRICT].
     * @throws UnclassifiedSql
     */
    public static function drop(Node $command, QueryContext $context): Constraint\DropConstraint
    {
        $name = Tree::child($command, ['name']) ?? throw new UnclassifiedSql('DROP CONSTRAINT requires its name.');
        $behavior = Tree::child($command, ['opt_drop_behavior']);
        return new Constraint\DropConstraint($context->tables->identifiers->name($name->tokens()[0]), in_array('IF_P', array_map(static fn ($token): string => $token->name, $command->tokens()), true), $behavior === null ? DropBehavior::Default : DropBehavior::from(strtoupper(Tree::text($behavior))));
    }
}
