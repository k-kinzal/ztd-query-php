<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Type\NullDomain;
use SqlSemantics\Statement\Type\SqliteChoiceDomain;
use SqlSemantics\Statement\Type\Unresolved;

#[CoversClass(SqliteChoiceDomain::class)]
#[Small]
final class SqliteChoiceDomainTest extends TestCase
{
    public function testAlternativesFlattensNestedChoicesAndRetainsOriginalTypes(): void
    {
        $integer = new TypeDescriptor(Builtin::Integer);
        $text = new TypeDescriptor(Builtin::Text);
        $domain = new SqliteChoiceDomain($integer, new SqliteChoiceDomain($text, new TypeDescriptor(Builtin::Integer)), NullDomain::Null, Unresolved::MissingDeclaration);
        self::assertSame([$integer, $text, NullDomain::Null, Unresolved::MissingDeclaration], $domain->alternatives);
    }
}
