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
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\MatchKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\QuantifiedSubquery;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\Quantifier;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

#[CoversClass(QuantifiedSubquery::class)]
#[Small]
final class QuantifiedSubqueryTest extends TestCase
{
    public function testDeriveScalarComparesWithTheColumn(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true);
        $query = self::createConfiguredStub(Query::class, ['deriveQuery' => new QueryFact([new Field(0, new OutputSlot(new Name('a'), new Known(Builtin::Int4), Nullability::NotNull))], $context->columnNames)]);
        $derivation = new Derivation($context);
        $fact = $derivation->scalar(new QuantifiedSubquery(new Constant(new IntegerConstant('1')), new OperatorName(new Name('<')), Quantifier::All, $query), $derivation->environment());
        self::assertEquals(new Known(Builtin::Bool), $fact->type);
    }

    public function testRenderWritesAKeywordOperator(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new QuantifiedSubquery(new NullLiteral(), MatchKeyword::NotILike, Quantifier::Some, self::createStub(Query::class)))->render($out);
        self::assertSame('NULL NOT ILIKE SOME ()', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsASumBeforeAProductOperator(): void
    {
        $sum = new BinaryOperation(new OperatorName(new Name('+')), new NullLiteral(), new NullLiteral());
        $this->expectExceptionMessage('The compared value needs parentheses to keep its place.');
        new QuantifiedSubquery($sum, new OperatorName(new Name('*')), Quantifier::Any, self::createStub(Query::class));
    }
}
