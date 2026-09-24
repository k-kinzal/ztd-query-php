<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\TypeSystem;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Ast\TypeReader;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Definition\Catalog\ObjectAddresses;
use SqlSemantics\Binding\Statement\Routine\Targets;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Catalog\Kind\OperatorSetKind;
use SqlSemantics\Model\Definition\Catalog\OperatorSetIdentity;
use SqlSemantics\Model\Definition\TypeSystem\OperatorSet as Member;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Operator as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Binds the operator class and operator family commands.
 * @visibility SqlSemantics
 */
final class OperatorSets
{
    /**
     * CREATE OPERATOR FAMILY names the family and its access method.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function createFamily(Origin $origin, Node $source, QueryContext $context): Statement\CreateOperatorFamilyStatement
    {
        return new Statement\CreateOperatorFamilyStatement($origin, self::name($source, $context), self::method($source, $context));
    }

    /**
     * CREATE OPERATOR CLASS lists operators, support functions, and at most one storage type.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function createClass(Origin $origin, Node $source, QueryContext $context): Statement\CreateOperatorClassStatement
    {
        $members = array_map(static fn (Node $item): Member\OperatorSetMember => self::member($item, $context), Tree::outer($source, ['opclass_item']));
        $opfamily = Tree::child($source, ['opt_opfamily']);
        $family = $opfamily === null ? null : Tree::child($opfamily, ['any_name']);
        $type = (new TypeReader(Dialect::PostgreSql))->read(Tree::child($source, ['Typename']) ?? throw new UnclassifiedSql('An operator class requires its indexed type.'));
        try {
            return new Statement\CreateOperatorClassStatement($origin, self::name($source, $context), $type, self::method($source, $context), Collections::nonEmpty($members), Tree::child($source, ['opt_default']) !== null, $family === null ? null : ObjectAddresses::name($family, $context, 2));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::OperatorClassMember, $source, $error);
        }
    }

    /**
     * ALTER OPERATOR FAMILY adds operators with operand types and support functions, or drops members by number and types.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function alterFamily(Origin $origin, Node $source, QueryContext $context): Statement\AddOperatorFamilyMembersStatement|Statement\DropOperatorFamilyMembersStatement
    {
        $name = self::name($source, $context);
        $method = self::method($source, $context);
        if (Tree::child($source, ['opclass_drop_list']) !== null) {
            return new Statement\DropOperatorFamilyMembersStatement($origin, $name, $method, Collections::nonEmpty(array_map(static fn (Node $item): Member\MemberRemoval => self::removal($item), Tree::outer($source, ['opclass_drop']))));
        }
        $members = [];
        foreach (Tree::outer($source, ['opclass_item']) as $item) {
            $member = self::member($item, $context);
            if (!($member instanceof Member\SupportFunctionMember || ($member instanceof Member\OperatorMember && $member->left !== null))) {
                throw new InvalidSql(InputViolation::OperatorClassMember, $item);
            }
            $members[] = $member;
        }
        return new Statement\AddOperatorFamilyMembersStatement($origin, $name, $method, Collections::nonEmpty($members));
    }

    /**
     * Reads one OPERATOR, FUNCTION, or STORAGE item; an operator is binary and RECHECK is ignored as the server ignores it.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function member(Node $item, QueryContext $context): Member\OperatorSetMember
    {
        $keyword = strtoupper($item->tokens()[0]->text ?? '');
        $number = Tree::child($item, ['Iconst']);
        try {
            if ($keyword === 'STORAGE') {
                return new Member\StorageMember((new TypeReader(Dialect::PostgreSql))->read(Tree::child($item, ['Typename']) ?? throw new UnclassifiedSql('STORAGE requires its type.')));
            }
            if ($keyword === 'FUNCTION') {
                [$left, $right] = self::types(Tree::child($item, ['type_list']));
                return new Member\SupportFunctionMember(self::number($number ?? $item), Targets::routine(Tree::child($item, ['function_with_argtypes']) ?? throw new UnclassifiedSql('FUNCTION requires its function.'), $context), $left, $right);
            }
            $signature = Tree::child($item, ['operator_with_argtypes']);
            $operator = $signature === null ? null : ObjectAddresses::operator($signature, $context);
            $clause = Tree::child($item, ['opclass_purpose']);
            $purpose = $clause === null ? null : Tree::child($clause, ['any_name']);
            $name = $operator !== null ? $operator->name : ObjectAddresses::name(Tree::child($item, ['any_operator']) ?? throw new UnclassifiedSql('OPERATOR requires its operator.'), $context, 2);
            return new Member\OperatorMember(self::number($number ?? $item), $name, $operator?->left, $operator?->right, $purpose === null ? null : ObjectAddresses::name($purpose, $context, 2));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::OperatorClassMember, $item, $error);
        }
    }

    /**
     * Reads one DROP OPERATOR or DROP FUNCTION item.
     * @throws InvalidSql
     */
    public static function removal(Node $item): Member\MemberRemoval
    {
        [$left, $right] = self::types(Tree::child($item, ['type_list']));
        try {
            return new Member\MemberRemoval(Member\MemberKind::from(strtoupper($item->tokens()[0]->text ?? '')), self::number(Tree::child($item, ['Iconst']) ?? $item), $left ?? throw new InvalidSql(InputViolation::OperatorClassMember, $item), $right ?? throw new InvalidSql(InputViolation::OperatorClassMember, $item));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::OperatorClassMember, $item, $error);
        }
    }

    /**
     * One or two associated types; one type stands for both, and no list for neither.
     * @return array{TypeDescriptor|null, TypeDescriptor|null}
     * @throws InvalidSql
     */
    public static function types(?Node $list): array
    {
        $types = $list === null ? [] : array_map(static fn (Node $type): TypeDescriptor => (new TypeReader(Dialect::PostgreSql))->read($type), Tree::outer($list, ['Typename']));
        if ($list !== null && ($types === [] || count($types) > 2)) {
            throw new InvalidSql(InputViolation::OperatorClassMember, $list);
        }
        return [$types[0] ?? null, $types[1] ?? $types[0] ?? null];
    }

    /**
     * A strategy or support number written as an integer constant.
     * @throws InvalidSql
     */
    public static function number(Node $source): int
    {
        $digits = ltrim(str_replace('_', '', Tree::text($source)), '0');
        return preg_match('/^[0-9]{0,5}$/D', $digits) === 1 ? (int) $digits : throw new InvalidSql(InputViolation::OperatorClassMember, $source);
    }

    /**
     * DROP OPERATOR CLASS and DROP OPERATOR FAMILY address one object by name and access method.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function drop(Origin $origin, Node $source, QueryContext $context): Statement\DropOperatorSetStatement
    {
        $kind = $source->name === 'DropOpClassStmt' ? OperatorSetKind::OperatorClass : OperatorSetKind::OperatorFamily;
        return new Statement\DropOperatorSetStatement($origin, new OperatorSetIdentity($kind, self::name($source, $context), self::method($source, $context)), in_array('EXISTS', DefinitionWords::of($source), true), Domains::behavior($source));
    }

    /**
     * The operator class or family name.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function name(Node $source, QueryContext $context): QualifiedName
    {
        return ObjectAddresses::name(Tree::child($source, ['any_name']) ?? throw new UnclassifiedSql('An operator class or family requires its name.'), $context, 2);
    }

    /**
     * The index access method after USING.
     * @throws UnclassifiedSql
     */
    public static function method(Node $source, QueryContext $context): string
    {
        return $context->tables->identifiers->name((Tree::child($source, ['name']) ?? throw new UnclassifiedSql('An operator class or family requires its access method.'))->tokens()[0]);
    }
}
