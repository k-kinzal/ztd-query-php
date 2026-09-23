<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Validation\ParameterSpelling;

#[CoversClass(ParameterSpelling::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ParameterSpellingTest extends TestCase
{
    #[TestWith([Dialect::MySql, '?'])]
    #[TestWith([Dialect::PostgreSql, '$1'])]
    #[TestWith([Dialect::Sqlite, ':name'])]
    #[TestWith([Dialect::Sqlite, '?24'])]
    #[TestWith([Dialect::Sqlite, '$namespace::name(suffix)'])]
    public function testAcceptsOneDatabaseParameter(Dialect $dialect, string $name): void
    {
        self::assertTrue(ParameterSpelling::accepts($name, $dialect));
    }

    #[TestWith([Dialect::PostgreSql, '$1; DROP TABLE t'])]
    #[TestWith([Dialect::MySql, '? + 1'])]
    #[TestWith([Dialect::Sqlite, ':name -- comment'])]
    #[TestWith([Dialect::PostgreSql, ':named'])]
    #[TestWith([Dialect::MySql, 'name'])]
    public function testAcceptsRejectsFragmentsAndTheWrongParameterSyntax(Dialect $dialect, string $name): void
    {
        self::assertFalse(ParameterSpelling::accepts($name, $dialect));
    }
}
