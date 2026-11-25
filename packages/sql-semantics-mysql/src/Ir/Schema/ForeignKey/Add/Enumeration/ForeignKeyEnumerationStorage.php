<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration;

use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyTextCollation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * One native ordinal or member-mask field in a physical comparison.
 * @visibility public
 * @example Reading the first ordinal label
 *     $kind = \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration\ForeignKeyEnumerationKind::Enum;
 *     $label = new \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration\ForeignKeyEnumerationMember('open');
 *     $collation = \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyTextCollation::Binary;
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration\ForeignKeyEnumerationStorage($kind, [$label], $collation))->members[0]->value // => 'open'
 */
final class ForeignKeyEnumerationStorage
{
    use Snapshot;

    /**
     * @var non-empty-list<ForeignKeyEnumerationMember> The exact declaration slots in native order
     */
    public readonly array $members;

    /**
     * Checks label order and the native declaration-cardinality rules of the role.
     * @param array<array-key, object|array<array-key, object|scalar|null>|scalar|null> $members Settled ordered member values
     */
    public function __construct(
        public readonly ForeignKeyEnumerationKind $kind,
        array $members,
        public readonly ForeignKeyTextCollation $collation,
    ) {
        $checked = Check::listOf($members, ForeignKeyEnumerationMember::class, 'A native ENUM or SET field needs settled member slots.', 1);
        if ($kind === ForeignKeyEnumerationKind::Enum) {
            Check::input(count($checked) <= 65535, 'A native ENUM has at most 65535 declared slots.');
        } else {
            $distinct = [];
            foreach ($checked as $member) {
                Check::input(!str_contains($member->value, ','), 'A native SET member cannot contain a comma.');
                $distinct[':' . $member->value] = true;
            }
            Check::input(count($distinct) <= 64, 'A native SET requires a checked upper bound of 64 distinct members.');
        }
        $this->members = $checked;
    }

    /**
     * Reports the packed native width without using the displayed member lengths.
     */
    public function keyBytes(): int
    {
        $count = count($this->members);
        if ($this->kind === ForeignKeyEnumerationKind::Enum) {
            return $count < 256 ? 1 : 2;
        }
        $bytes = intdiv($count - 1, 8) + 1;

        return $bytes > 4 ? 8 : $bytes;
    }

    /**
     * Checks that all settled labels can belong to the release's native field dictionary.
     */
    public function checkRelease(bool $legacy): void
    {
        foreach ($this->members as $member) {
            $valid = $legacy ? !str_contains($member->value, "\0") : !str_ends_with($member->value, ' ');
            Check::input($valid, 'A native ENUM or SET label must match its release dictionary format.');
        }
    }
}
