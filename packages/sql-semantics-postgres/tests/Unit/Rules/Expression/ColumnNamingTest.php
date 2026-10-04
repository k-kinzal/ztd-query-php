<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\ColumnNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Cast;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\CastSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\CaseBranch;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\CaseExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\KeywordDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\TypeKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ColumnNaming::class)]
#[Small]
final class ColumnNamingTest extends TestCase
{
    public function testNameOfACastOfACaseIsTheType(): void
    {
        $case = new CaseExpression(null, [new CaseBranch(new BooleanLiteral(true), new NullLiteral())]);
        self::assertSame('int4', (new ColumnNaming())->name(new Cast($case, new TypeName(new KeywordDesignation(TypeKeyword::Integer)), CastSpelling::Function))?->value);
    }

    public function testFigureAnswersTheStrength(): void
    {
        self::assertEquals([[new Name('a'), 2], [null, 0]], [(new ColumnNaming())->figure(new ColumnReference([new Name('a')])), (new ColumnNaming())->figure(new NullLiteral())]);
    }
}
