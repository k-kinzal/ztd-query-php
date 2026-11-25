<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyIntegerDomain;
use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;

#[CoversClass(ForeignKeyIntegerDomain::class)]
#[Small]
final class ForeignKeyIntegerDomainTest extends TestCase
{
    public function testChildKeyBytesUsesNativeStorageWidth(): void
    {
        $field = new ForeignKeyIntegerDomain(IntegerStorage::Medium, true);

        self::assertSame(3, $field->childKeyBytes());
    }
}
