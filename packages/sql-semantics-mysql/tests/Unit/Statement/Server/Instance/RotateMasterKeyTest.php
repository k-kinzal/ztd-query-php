<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\RotateMasterKey;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(RotateMasterKey::class)]
#[Medium]
final class RotateMasterKeyTest extends TestCase
{
    public function testRenderWritesTheKeyringName(): void
    {
        self::assertSame('ALTER INSTANCE ROTATE innodb MASTER KEY', (new Inspection('mysql-5.7.44'))->inspect("alter instance rotate 'innodb' master key")->toString());
    }

    public function testBinaryLogTellsTheKeyring(): void
    {
        self::assertTrue((new RotateMasterKey(new Name('BinLog')))->binaryLog());
        self::assertFalse((new RotateMasterKey(new Name('InnoDB')))->binaryLog());
    }
}
