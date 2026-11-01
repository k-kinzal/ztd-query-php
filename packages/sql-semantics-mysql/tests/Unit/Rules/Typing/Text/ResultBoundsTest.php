<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Typing\Text\ResultBounds;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(ResultBounds::class)]
#[Small]
final class ResultBoundsTest extends TestCase
{
    public function testComputedSeparatesBytesFromCharactersExactlyOnce(): void
    {
        $rule = new ResultBounds();
        $domain = $rule->computed(Domain::string(68000, Collation::known('utf8mb4_bin'), Field::MediumBlob));

        self::assertSame([17000, 68000], [$domain->length, $domain->metadataLength()]);
        self::assertSame($domain, $rule->computed($domain));
        $number = Domain::integer();
        self::assertSame($number, $rule->computed($number));
    }

    public function testSelectedKeepsPhysicalBoundsAndChoosesLegacyComputedFields(): void
    {
        $rule = new ResultBounds();
        $physical = new Domain(Kind::String, Field::Blob, 255, 0, false, Collation::known('utf8mb4_bin'));
        $modern = $rule->selected($physical, false, true);
        $legacy = $rule->selected($physical, true, true);

        self::assertSame([Field::Blob, 255, 1020, Domain::NOT_FIXED], [$modern->field, $modern->length, $modern->metadataLength(), $modern->decimals]);
        self::assertSame([Field::VarString, 255, 1020, Domain::NOT_FIXED], [$legacy->field, $legacy->length, $legacy->metadataLength(), $legacy->decimals]);
        $number = Domain::integer();
        self::assertSame($number, $rule->selected($number, true, true));
    }
}
