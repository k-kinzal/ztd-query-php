<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Type\NullDomain;

#[CoversClass(NullConstant::class)]
#[Small]
final class NullConstantTest extends TestCase
{
    public function testTypeIdentifiesTheKnownNullValueBeforeContextualCoercion(): void
    {
        self::assertSame(NullDomain::Null, (new NullConstant())->type());
    }

    public function testNullabilityIsAlwaysNull(): void
    {
        self::assertSame(Nullability::AlwaysNull, (new NullConstant())->nullability());
    }

    public function testReferencesDoesNotInventAnOwnerForTheConstant(): void
    {
        self::assertSame([], (new NullConstant())->references());
    }

    #[TestWith(['NULL'])]
    #[TestWith(['null'])]
    #[TestWith(['nUlL'])]
    public function testToStringPreservesTheUnaliasedDatabaseLabel(string $keyword): void
    {
        $db = new PDO('sqlite::memory:');
        $constant = new NullConstant($keyword);
        $result = $db->query('SELECT ' . $constant->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame([$keyword => null], $result->fetch(PDO::FETCH_ASSOC));
    }
}
