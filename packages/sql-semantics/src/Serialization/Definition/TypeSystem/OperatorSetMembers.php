<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\TypeSystem;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\OperatorSet as Member;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Serialization\Definition\Catalog\ObjectAddresses;
use SqlSemantics\Serialization\Definition\Routines;
use SqlSemantics\Serialization\TypeDeclaration;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Writes operator class and operator family members.
 * @visibility SqlSemantics
 */
final class OperatorSetMembers
{
    /**
     * One OPERATOR, FUNCTION, or STORAGE item.
     * @throws InvalidStructure
     */
    public static function member(Member\OperatorSetMember $member): Tree
    {
        return match (true) {
            $member instanceof Member\OperatorMember => new Tree('operator-member', [Build::keyword('OPERATOR ' . $member->strategy), ObjectAddresses::operator($member->operator), ...self::types($member->left, $member->right), ...($member->orderFamily === null ? [] : [Build::keyword('FOR ORDER BY'), Build::identifier($member->orderFamily->parts, Dialect::PostgreSql)])]),
            $member instanceof Member\SupportFunctionMember => new Tree('function-member', [Build::keyword('FUNCTION ' . $member->number), ...self::types($member->left, $member->right), Routines::routine($member->function)]),
            $member instanceof Member\StorageMember => new Tree('storage-member', [Build::keyword('STORAGE'), TypeDeclaration::write($member->type)]),
            default => throw new InvalidStructure('Unclassified operator class member: ' . $member::class),
        };
    }

    /**
     * One DROP OPERATOR or DROP FUNCTION item with both associated types.
     * @throws InvalidStructure
     */
    public static function removal(Member\MemberRemoval $removal): Tree
    {
        return new Tree('member-removal', [Build::keyword($removal->kind->value . ' ' . $removal->number), ...self::types($removal->left, $removal->right)]);
    }

    /**
     * The parenthesized associated types, when present.
     * @return list<Tree>
     * @throws InvalidStructure
     */
    public static function types(?TypeDescriptor $left, ?TypeDescriptor $right): array
    {
        return $left === null || $right === null ? [] : [Build::parentheses(Build::separated([TypeDeclaration::write($left), TypeDeclaration::write($right)]))];
    }
}
