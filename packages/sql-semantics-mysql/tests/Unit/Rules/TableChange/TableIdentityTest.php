<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\TableChange\TableIdentity;
use SqlSemantics\Platform\MySql\Statement\Name\EmptyObjectName;
use SqlSemantics\Platform\MySql\Statement\Name\ObjectNameKind;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(TableIdentity::class)]
#[Small]
final class TableIdentityTest extends TestCase
{
    public function testRepeatedSeparatesIdenticalNamesFromContextDependentEquality(): void
    {
        $first = new QualifiedName(new Name('t'));
        $qualified = new QualifiedName(new Name('t'), new Name('d'));
        $repeated = new QualifiedName(new Name('t'));
        $identity = new TableIdentity();

        self::assertNull($identity->repeated([$first, $qualified]));
        self::assertSame($repeated, $identity->repeated([$first, $qualified, $repeated])?->name);
    }

    /**
     * @return iterable<string, array{QualifiedName, ObjectNameKind}>
     */
    public static function providerInvalidNames(): iterable
    {
        yield 'empty table' => [new QualifiedName(new Name('')), ObjectNameKind::Table];
        yield 'empty qualified table' => [new QualifiedName(new Name(''), new Name('db')), ObjectNameKind::Table];
        yield 'empty database' => [new QualifiedName(new Name('t'), new Name('')), ObjectNameKind::Database];
        yield 'both empty' => [new QualifiedName(new Name(''), new Name('')), ObjectNameKind::Database];
    }

    #[DataProvider('providerInvalidNames')]
    public function testProblemDistinguishesAnEmptyQualifierFromTheSessionDatabase(QualifiedName $name, ObjectNameKind $kind): void
    {
        self::assertEquals(new EmptyObjectName($kind), (new TableIdentity())->problem($name));
    }

    #[DataProvider('providerInvalidNames')]
    public function testCheckPreventsPublishingAnEmptyTableIdentity(QualifiedName $name, ObjectNameKind $kind): void
    {
        self::assertSame($kind, (new TableIdentity())->problem($name)?->kind);
        $this->expectExceptionMessage('A table name and its explicit database qualifier are nonempty.');

        new TableReference($name);
    }

    public function testCheckPreservesEmptyAliasesAndAbsentDatabaseQualifiers(): void
    {
        $name = new QualifiedName(new Name('t'));
        $table = new TableReference($name, alias: new Name(''));

        self::assertSame($name, $table->name);
        self::assertSame('', $table->alias?->value);
        self::assertNull((new TableIdentity())->problem($name));
    }
}
