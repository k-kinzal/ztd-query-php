<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Ownership;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Ownership\OwnershipCommands;

#[CoversClass(OwnershipCommands::class)]
#[Medium]
final class OwnershipCommandsTest extends TestCase
{
    public function testWriteReturnsNullForUnrelatedRequests(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertNull(OwnershipCommands::write($statement));
    }

    #[TestWith(['DROP OWNED BY alice, CURRENT_ROLE'])]
    #[TestWith(['DROP OWNED BY "a""b", SESSION_USER RESTRICT'])]
    #[TestWith(['DROP OWNED BY "CURRENT_USER" CASCADE'])]
    #[TestWith(['REASSIGN OWNED BY alice, CURRENT_USER TO "CURRENT_USER"'])]
    public function testWriteKeepsTheRoleDomainsAndPoliciesAcrossBinding(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        $rebound = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($statement::class, $rebound::class);
        self::assertSame($statement->kind, $rebound->kind);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($rebound));
    }

    public function testRoleQuotesIdentifierPunctuationWithoutChangingTheRole(): void
    {
        $sql = OwnershipCommands::role(new NamedRole('x"y'))->toString();
        self::assertSame('"x""y"', $sql);
    }

}
