<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Role\RoleKeyword;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\CreateRoleStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RoleKeyword::class)]
#[Medium]
final class RoleKeywordTest extends TestCase
{
    #[TestWith([RoleKeyword::Role, 'ROLE'])]
    #[TestWith([RoleKeyword::User, 'USER'])]
    #[TestWith([RoleKeyword::Group, 'GROUP'])]
    public function testEachKeywordSpellsItselfAndSurvivesADefinition(RoleKeyword $keyword, string $word): void
    {
        self::assertSame($word, $keyword->value);
        self::assertSame($keyword, RoleKeyword::from($word));
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE ' . $word . ' r');
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        self::assertSame($keyword, $statement->keyword);
        self::assertSame([], $statement->options);
        self::assertSame('CREATE ' . $word . ' "r"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $rebound = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(CreateRoleStatement::class, $rebound);
        self::assertSame($keyword, $rebound->keyword);
    }

    public function testTheEnumerationListsTheThreeKeywords(): void
    {
        self::assertSame([RoleKeyword::Role, RoleKeyword::User, RoleKeyword::Group], RoleKeyword::cases());
    }
}
