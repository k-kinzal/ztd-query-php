<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Kind;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;

#[CoversClass(IntegralKind::class)]
#[Small]
final class IntegralKindTest extends TestCase
{
    public function testCasesSpellTheKeywordOfEveryIntegerType(): void
    {
        self::assertSame(['TinyInt', 'SmallInt', 'MediumInt', 'Int', 'BigInt'], array_column(IntegralKind::cases(), 'name'));
        self::assertSame(['TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'BIGINT'], array_column(IntegralKind::cases(), 'value'));
    }
}
