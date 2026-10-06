<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Reference\Missing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredDomain;

#[CoversClass(UndeclaredDomain::class)]
#[Small]
final class UndeclaredDomainTest extends TestCase
{
    public function testDescribeNamesTheType(): void
    {
        $name = new QualifiedName(new Name('mood'), new Name('app'));

        $missing = new UndeclaredDomain($name);

        self::assertSame('the definition of data type mood', $missing->describe());
        self::assertSame($name, $missing->name);
    }
}
