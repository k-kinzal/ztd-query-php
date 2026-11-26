<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringDomain;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringKind;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringStorage;

#[CoversClass(ForeignKeyStringDomain::class)]
#[Small]
final class ForeignKeyStringDomainTest extends TestCase
{
    public function testChildKeyBytesUsesTheOrderedChildStorage(): void
    {
        $child = new ForeignKeyStringStorage(ForeignKeyStringKind::Varbinary, 35);
        $target = new ForeignKeyStringStorage(ForeignKeyStringKind::Binary, 17);
        $pair = new ForeignKeyStringDomain($child, $target);

        self::assertSame(35, $pair->childKeyBytes());
        self::assertSame(17, $pair->parent->length);
    }
}
