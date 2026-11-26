<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration;

use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyIntegerDomain;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarKind;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarStorage;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A native unsigned field comparison with at least one ENUM or SET role.
 * @visibility public
 * @example Binding two fields that use the same native ordinal width
 *     $kind = \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration\ForeignKeyEnumerationKind::Enum;
 *     $member = new \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration\ForeignKeyEnumerationMember('ready');
 *     $collation = \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyTextCollation::Binary;
 *     $field = new \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration\ForeignKeyEnumerationStorage($kind, [$member], $collation);
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration\ForeignKeyEnumerationDomain($field, $field))->childKeyBytes() // => 1
 */
final class ForeignKeyEnumerationDomain
{
    use Snapshot;

    /**
     * Compares fixed unsigned key widths while retaining the exact role of each field.
     */
    public function __construct(
        public readonly ForeignKeyEnumerationStorage|ForeignKeyIntegerDomain|ForeignKeyScalarStorage $child,
        public readonly ForeignKeyEnumerationStorage|ForeignKeyIntegerDomain|ForeignKeyScalarStorage $parent,
    ) {
        Check::input(
            $child instanceof ForeignKeyEnumerationStorage || $parent instanceof ForeignKeyEnumerationStorage,
            'An ENUM or SET comparison needs a declared label-based field.'
        );
        $left = $this->unsignedBytes($child);
        $right = $this->unsignedBytes($parent);
        Check::input($left !== null && $left === $right, 'A native ENUM or SET reference requires equal-width unsigned field encodings.');
    }

    /**
     * Reports the established native width of the referencing key.
     */
    public function childKeyBytes(): int
    {
        return $this->child instanceof ForeignKeyIntegerDomain ? $this->child->childKeyBytes() : $this->child->keyBytes();
    }

    /**
     * Returns the full width of a field that participates in the unsigned comparison.
     */
    public function unsignedBytes(ForeignKeyEnumerationStorage|ForeignKeyIntegerDomain|ForeignKeyScalarStorage $field): ?int
    {
        if ($field instanceof ForeignKeyIntegerDomain) {
            return $field->unsigned ? $field->childKeyBytes() : null;
        }
        if ($field instanceof ForeignKeyScalarStorage) {
            return $field->kind === ForeignKeyScalarKind::Year ? $field->keyBytes() : null;
        }

        return $field->keyBytes();
    }

    /**
     * Checks the release-dependent storage conditions at both reference endpoints.
     */
    public function checkRelease(bool $legacy): void
    {
        foreach ([$this->child, $this->parent] as $field) {
            if ($field instanceof ForeignKeyEnumerationStorage) {
                $field->checkRelease($legacy);
            }
        }
    }
}
