<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\PublicRole;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PublicRole::class)]
#[Medium]
final class PublicRoleTest extends TestCase
{
    public function testThePseudoRoleSpellsThePublicKeyword(): void
    {
        self::assertSame('PUBLIC', PublicRole::Public->value);
        self::assertSame(PublicRole::Public, PublicRole::from('PUBLIC'));
        self::assertSame([PublicRole::Public], PublicRole::cases());
    }

    public function testThePublicGranteeIsRetainedWithoutExpansion(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('GRANT SELECT ON TABLE t TO PUBLIC, alice');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertEquals([PublicRole::Public, new NamedRole('alice')], $statement->grantees);
        self::assertSame('GRANT SELECT ON TABLE "public"."t" TO PUBLIC, "alice"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $rebound = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(GrantPrivilegesStatement::class, $rebound);
        self::assertSame(PublicRole::Public, $rebound->grantees[0]);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($rebound));
    }

    public function testAQuotedPublicNameStaysANamedRole(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('GRANT SELECT ON TABLE t TO "Public"');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertEquals([new NamedRole('Public')], $statement->grantees);
        self::assertSame('GRANT SELECT ON TABLE "public"."t" TO "Public"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
