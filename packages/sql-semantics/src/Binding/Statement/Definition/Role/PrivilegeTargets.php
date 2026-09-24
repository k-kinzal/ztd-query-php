<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Role;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Query\TableOccurrence;
use SqlSemantics\Binding\Statement\Routine\Targets;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\LargeObjectTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ParameterTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\RoutineClass;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\RoutineTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaObjectClass;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaObjectTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaScopedClass;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaScopedTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ServerObjectClass;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ServerObjectTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\TableTargets;
use SqlSemantics\Model\Definition\Routine\RoutineByName;
use SqlSemantics\Model\Definition\Routine\RoutineBySignature;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Classifies privilege targets by object class, resolving tables against the schema snapshot.
 * @visibility SqlSemantics
 */
final class PrivilegeTargets
{
    /**
     * A bare name list is a table list.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function read(Node $target, Origin $origin, QueryContext $context): TableTargets|SchemaObjectTargets|ServerObjectTargets|RoutineTargets|LargeObjectTargets|ParameterTargets|SchemaScopedTargets
    {
        $identifiers = $context->tables->identifiers;
        $children = Tree::significant($target);
        $first = $children[0] ?? throw new UnclassifiedSql('A privilege target requires its object selection.');
        if ($first instanceof Node) {
            return self::tables($first, $origin, $context);
        }
        $word = strtoupper($first->text);
        $second = ($children[1] ?? null) instanceof Token ? strtoupper($children[1]->text) : '';
        return match ($word) {
            'TABLE' => self::tables(self::list($target, 'qualified_name_list'), $origin, $context),
            'SEQUENCE' => new SchemaObjectTargets(SchemaObjectClass::Sequence, self::qualified(self::list($target, 'qualified_name_list'), 'qualified_name', $identifiers)),
            'DOMAIN', 'TYPE' => new SchemaObjectTargets(SchemaObjectClass::from($word), self::qualified(self::list($target, 'any_name_list'), 'any_name', $identifiers)),
            'FOREIGN' => new ServerObjectTargets($second === 'DATA' ? ServerObjectClass::ForeignDataWrapper : ServerObjectClass::ForeignServer, self::names(self::list($target, 'name_list'), $identifiers)),
            'DATABASE', 'LANGUAGE', 'SCHEMA', 'TABLESPACE' => new ServerObjectTargets(ServerObjectClass::from($word), self::names(self::list($target, 'name_list'), $identifiers)),
            'FUNCTION', 'PROCEDURE', 'ROUTINE' => new RoutineTargets(RoutineClass::from($word), Collections::nonEmpty(array_map(static fn (Node $routine): RoutineByName|RoutineBySignature => Targets::routine($routine, $context), Tree::outer(self::list($target, 'function_with_argtypes_list'), ['function_with_argtypes'])))),
            'LARGE' => new LargeObjectTargets(self::objectIds(self::list($target, 'NumericOnly_list'))),
            'PARAMETER' => new ParameterTargets(Collections::nonEmpty(array_map(static fn (Node $name): QualifiedName => new QualifiedName($identifiers->parts($name)), Tree::outer(self::list($target, 'parameter_name_list'), ['parameter_name'])))),
            'ALL' => new SchemaScopedTargets(SchemaScopedClass::from($second), self::names(self::list($target, 'name_list'), $identifiers)),
            default => throw new UnclassifiedSql('Unclassified privilege target: ' . Tree::text($target)),
        };
    }

    /**
     * @throws UnclassifiedSql
     */
    public static function list(Node $target, string $rule): Node
    {
        return Tree::child($target, [$rule]) ?? throw new UnclassifiedSql('A privilege target requires its ' . $rule . '.');
    }

    /**
     * Unknown tables are diagnosed as unresolved declarations rather than rejected.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function tables(Node $list, Origin $origin, QueryContext $context): TableTargets
    {
        $tables = [];
        foreach (Tree::outer($list, ['qualified_name']) as $name) {
            self::relation($name, $context->tables->identifiers);
            $reference = TableOccurrence::resolve($name, $context, $origin->scopeId);
            $tables[] = $reference instanceof TableReference ? $reference : throw new UnclassifiedSql('A privilege table target cannot restrict descendants.');
        }
        return new TableTargets(Collections::nonEmpty($tables));
    }

    /**
     * @return non-empty-list<QualifiedName>
     * @throws InvalidSql
     */
    public static function qualified(Node $list, string $rule, Identifiers $identifiers): array
    {
        $names = [];
        foreach (Tree::outer($list, [$rule]) as $name) {
            self::relation($name, $identifiers);
            $names[] = new QualifiedName($identifiers->parts($name));
        }
        return Collections::nonEmpty($names);
    }

    /**
     * Subscripts, wildcards, and more than three components are not object names.
     * @throws InvalidSql
     */
    public static function relation(Node $name, Identifiers $identifiers): void
    {
        foreach (Tree::outer($name, ['indirection_el']) as $component) {
            if (Tree::child($component, ['attr_name']) === null) {
                throw new InvalidSql(InputViolation::RelationName, $name);
            }
        }
        $parts = $identifiers->parts($name);
        if (count($parts) > 3 || in_array('', $parts, true)) {
            throw new InvalidSql(InputViolation::RelationName, $name);
        }
    }

    /**
     * @return non-empty-list<string>
     */
    public static function names(Node $list, Identifiers $identifiers): array
    {
        return Collections::nonEmpty(array_map(static fn (Node $name): string => $identifiers->name($name->tokens()[0]), Tree::outer($list, ['name'])));
    }

    /**
     * Large object identifiers are unsigned 32-bit OIDs; other numerics are diagnosed.
     * @return non-empty-list<int>
     * @throws InvalidSql
     */
    public static function objectIds(Node $list): array
    {
        $ids = [];
        foreach (Tree::outer($list, ['NumericOnly']) as $number) {
            $text = str_replace(' ', '', Tree::text($number));
            $digits = ltrim($text, '+-');
            if (!ctype_digit($digits) || strlen(ltrim($digits, '0')) > 10 || (int) $digits > 4294967295 || (str_starts_with($text, '-') && (int) $digits !== 0)) {
                throw new InvalidSql(InputViolation::LargeObjectId, $number);
            }
            $ids[] = (int) $digits;
        }
        return Collections::nonEmpty($ids);
    }
}
