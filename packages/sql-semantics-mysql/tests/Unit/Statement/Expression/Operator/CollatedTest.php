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
    public function testDeriveScalarStoresALegacyProgramCollationForTheInvocation(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.7.44');
        $definition = $semantics->analyze("CREATE PROCEDURE p() SELECT 'x' COLLATE missing");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $definition);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection::class, $definition->body);
        $projection = $definition->body->items[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection::class, $projection);
        $stored = $projection->expression;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredCollated::class, $stored);

        self::assertSame([], $definition->diagnostics());
        self::assertSame([], $definition->warnings());
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation::class, $stored->problem());
        self::assertSame('missing', $stored->problem()->name);
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
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage("Unknown collation: 'zz'");
        (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT ROW(1, 2) COLLATE zz');
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

    public function testDeriveScalarIsNeverReachedForACollationTheReadingRefuses(): void
    {
        try {
            (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT (BINARY 1) COLLATE zz');
            self::fail('The collation does not exist.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation::class, $failure->problem);
        }
    }

    public function testAnEmptyCollationNameIsRefusedWhileReading(): void
    {
        try {
            (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT missing COLLATE ``');
            self::fail('The empty collation name is unknown.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation::class, $failure->problem);
            self::assertSame('', $failure->problem->name);
            self::assertFalse($failure->aborts);
        }
    }

}
