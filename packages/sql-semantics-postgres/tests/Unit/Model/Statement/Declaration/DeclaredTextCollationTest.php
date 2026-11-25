<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\DeclaredTextCollation;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;

#[CoversClass(DeclaredTextCollation::class)]
#[Small]
final class DeclaredTextCollationTest extends TestCase
{
    public function testResolve(): void
    {
        self::assertSame(
            DeclaredTextCollation::StockC,
            DeclaredTextCollation::resolve(new QualifiedName(new Name('C'), new Name('pg_catalog')), false)
        );
        self::assertNull(DeclaredTextCollation::resolve(new QualifiedName(new Name('C')), false));
        self::assertSame(DeclaredTextCollation::StockC, DeclaredTextCollation::resolve(new QualifiedName(new Name('C')), true));
        self::assertNull(DeclaredTextCollation::resolve(new QualifiedName(new Name('C'), new Name('decoy')), true));
        self::assertNull(DeclaredTextCollation::resolve(new QualifiedName(new Name('POSIX'), new Name('pg_catalog')), true));
    }
}
