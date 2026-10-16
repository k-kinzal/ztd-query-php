<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\BinaryCast;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Collated;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(Collated::class)]
#[Medium]
final class CollatedTest extends TestCase
{
    public function testDeriveScalarDefersLegacyProgramNameLookupUntilTheInstructionIsDerived(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.7.44');
        $definition = $semantics->analyze("CREATE PROCEDURE p() SELECT 'x' COLLATE missing");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $definition->statement);
        self::assertInstanceOf(\SqlSemantics\Statement\Statement::class, $definition->statement->body);
        $instruction = new \SqlSemantics\Statement\Operation($definition->context, $definition->statement->body);

        self::assertSame([], $definition->facts->diagnostics);
        self::assertSame([], $definition->facts->warnings);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation::class, $instruction->facts->diagnostics[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $instruction->facts->warnings[0]);
    }

    public function testDeriveScalarMakesCollatedResultsNullableFrom57(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-5.7.44', null, ParameterStyle::Native), null, [], true));
        $fact = $derivation->scalar(new Collated(new StringLiteral(['a']), new Name('utf8mb4_bin')), $derivation->environment());

        self::assertSame(\SqlSemantics\Statement\Type\Nullability::Nullable, $fact->nullability);
    }

    public function testDeriveScalarPreservesLegacyOperandNullability(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-5.6.51', null, ParameterStyle::Native), null, [], true));
        $fact = $derivation->scalar(new Collated(new StringLiteral(['a']), new Name('utf8mb4_bin')), $derivation->environment());

        self::assertSame(\SqlSemantics\Statement\Type\Nullability::NotNull, $fact->nullability);
    }

    public function testDeriveScalarHasTheTypeOfTheOperand(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));

        self::assertEquals(new Known(Domain::string(1, Collation::known('utf8mb4_bin'), Field::VarString, Coercibility::Explicit)), $derivation->scalar(new Collated(new StringLiteral(['a']), new Name('utf8mb4_bin')), $derivation->environment())->type);
    }

    public function testDeriveScalarReportsAnUnknownCollationBeforeTheOperand(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT ROW(1, 2) COLLATE zz');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation::class, $operation->facts->diagnostics[0]);
        self::assertCount(2, $operation->facts->diagnostics);
    }

    public function testRenderChainsCollationsFromTheLeft(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new Collated(new Collated(new StringLiteral(['a']), new Name('utf8mb4_bin')), new Name('utf8mb4_0900_ai_ci')))->render($out);

        self::assertSame("'a' COLLATE utf8mb4_bin COLLATE utf8mb4_0900_ai_ci", (new Lexical())->join($out->pieces()));
    }

    public function testABinaryCastOperandIsRejected(): void
    {
        $this->expectExceptionMessage('The operand of COLLATE needs a grouping to keep its place.');

        new Collated(new BinaryCast(new StringLiteral(['a'])), new Name('utf8mb4_bin'));
    }

    public function testDeriveScalarPlacesTheUnknownCollationAfterTheWarningsOfTheOperand(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT (BINARY 1) COLLATE zz');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecation::class, $operation->facts->warnings[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $operation->facts->warnings[1]);
    }

    public function testAnEmptyCollationNameIsResolvedAfterTheOperand(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT missing COLLATE ``');

        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Column\MissingColumn::class, $operation->facts->diagnostics[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation::class, $operation->facts->diagnostics[1]);
        self::assertSame([], $operation->facts->warnings);
    }

}
