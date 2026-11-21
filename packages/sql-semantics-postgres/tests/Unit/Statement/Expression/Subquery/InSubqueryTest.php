<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Subquery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Lexical;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\QueryFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Query;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\Field;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\OutputSlot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\Negation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\InSubquery;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

#[CoversClass(InSubquery::class)]
#[Small]
final class InSubqueryTest extends TestCase
{
    public function testDeriveScalarComparesWithTheColumn(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true);
        $query = self::createConfiguredStub(Query::class, ['deriveQuery' => new QueryFact([new Field(0, new OutputSlot(new Name('a'), new Known(Builtin::Int4), Nullability::NotNull))], $context->columnNames)]);
        $derivation = new Derivation($context);
        $fact = $derivation->scalar(new InSubquery(new Constant(new IntegerConstant('1')), true, $query), $derivation->environment());
        self::assertEquals(new Known(Builtin::Bool), $fact->type);
    }

    public function testRenderWritesNotIn(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new InSubquery(new NullLiteral(), true, self::createStub(Query::class)))->render($out);
        self::assertSame('NULL NOT IN ()', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsANegatedValue(): void
    {
        $this->expectExceptionMessage('The tested value needs parentheses to keep its place.');
        new InSubquery(new Negation(new NullLiteral()), false, self::createStub(Query::class));
    }
}
