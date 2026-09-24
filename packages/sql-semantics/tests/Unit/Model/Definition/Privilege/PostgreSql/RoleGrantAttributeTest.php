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
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\RevokeRolesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RoleGrantAttribute::class)]
#[Medium]
final class RoleGrantAttributeTest extends TestCase
{
    public function testRepresentsEveryMembershipOption(): void
    {
        self::assertSame(['ADMIN', 'INHERIT', 'SET'], array_column(RoleGrantAttribute::cases(), 'value'));
    }

    #[TestWith(['ADMIN', RoleGrantAttribute::Admin])]
    #[TestWith(['INHERIT', RoleGrantAttribute::Inherit])]
    #[TestWith(['SET', RoleGrantAttribute::Set])]
    public function testBindsTheRevokedOptionAndWritesItBack(string $word, RoleGrantAttribute $attribute): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('REVOKE ' . $word . ' OPTION FOR staff FROM alice');
        self::assertInstanceOf(RevokeRolesStatement::class, $statement);
        self::assertSame($attribute, $statement->option);
        self::assertSame('REVOKE ' . $word . ' OPTION FOR "staff" FROM "alice"', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
}
