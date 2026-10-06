<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Kind;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;

#[CoversClass(BinaryKind::class)]
#[Small]
final class BinaryKindTest extends TestCase
{
    public function testCasesSpellTheKeywordsOfEveryBinaryStringType(): void
    {
        self::assertSame(['Binary', 'VarBinary', 'TinyBlob', 'Blob', 'MediumBlob', 'LongBlob', 'LongVarBinary'], array_column(BinaryKind::cases(), 'name'));
        self::assertSame(['BINARY', 'VARBINARY', 'TINYBLOB', 'BLOB', 'MEDIUMBLOB', 'LONGBLOB', 'LONG VARBINARY'], array_column(BinaryKind::cases(), 'value'));
    }
}
