<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\RoleGrantAttribute;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\RoleGrantOption;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantRolesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RoleGrantOption::class)]
#[Medium]
final class RoleGrantOptionTest extends TestCase
{
    #[TestWith([RoleGrantAttribute::Set, false])]
    #[TestWith([RoleGrantAttribute::Admin, true])]
    public function testRetainsTheAttributeAndItsValue(RoleGrantAttribute $attribute, bool $granted): void
    {
        $option = new RoleGrantOption($attribute, $granted);
        self::assertSame($attribute, $option->attribute);
        self::assertSame($granted, $option->granted);
    }

    #[TestWith(['WITH ADMIN OPTION', RoleGrantAttribute::Admin, true, 'GRANT "staff" TO "alice" WITH ADMIN TRUE'])]
    #[TestWith(['WITH ADMIN TRUE', RoleGrantAttribute::Admin, true, 'GRANT "staff" TO "alice" WITH ADMIN TRUE'])]
    #[TestWith(['WITH SET FALSE', RoleGrantAttribute::Set, false, 'GRANT "staff" TO "alice" WITH SET FALSE'])]
    #[TestWith(['WITH INHERIT TRUE', RoleGrantAttribute::Inherit, true, 'GRANT "staff" TO "alice" WITH INHERIT TRUE'])]
    public function testBindsTheOptionSpellingsToOneValueAndWritesThemBack(string $clause, RoleGrantAttribute $attribute, bool $granted, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('GRANT staff TO alice ' . $clause);
        self::assertInstanceOf(GrantRolesStatement::class, $statement);
        self::assertEquals([new RoleGrantOption($attribute, $granted)], $statement->options);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($statement->toString())->toString());
    }
}
