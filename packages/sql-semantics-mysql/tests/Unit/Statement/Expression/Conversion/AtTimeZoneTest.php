<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Conversion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\TimeZone;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(AtTimeZone::class)]
#[Medium]
final class AtTimeZoneTest extends TestCase
{
    public function testInputRefusalRetainsAnUnsupportedZoneAsADeferredCondition(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
        $statement = $semantics->analyze("SELECT CAST(NULL AT TIME ZONE '10' AS DATETIME)");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody::class, $statement->query);
        $diagnostic = $statement->query->problem();
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\TimeZoneConversion::class, $diagnostic);
        self::assertFalse($diagnostic->operand);
        self::assertSame('10', $diagnostic->expression->zone->value);
        self::assertSame("SELECT CAST(NULL AT TIME ZONE '10' AS DATETIME)", $statement->toString());
    }

    public function testDeriveScalarIsADateTimeOfThePrecision(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $fact = $derivation->scalar(new AtTimeZone(new NullLiteral(), TimeZone::Utc, false, '6'), $derivation->environment());

        self::assertEquals(new Known(new Domain(Kind::DateTime, Field::DateTime, 26, 6, false, null, [], Coercibility::Numeric)), $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testRenderKeepsTheIntervalKeyword(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new AtTimeZone(new NullLiteral(), TimeZone::ZeroOffset, true))->render($out);

        self::assertSame("CAST(NULL AT TIME ZONE INTERVAL '+00:00' AS DATETIME)", (new Lexical())->join($out->pieces()));
    }

    public function testInputRefusalRetainsTheConversionWithoutPublishingAnImpossibleScalar(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
        $statement = $semantics->analyze("SELECT CAST(CAST(1 AS SIGNED) AT TIME ZONE '+00:00' AS DATETIME), missing");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody::class, $statement->query);
        $diagnostic = $statement->query->problem();
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\TimeZoneConversion::class, $diagnostic);
        $item = $statement->query->selection->items[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection::class, $item);
        self::assertSame($item->expression, $diagnostic->expression);
        self::assertTrue($diagnostic->operand);
        self::assertSame('Cannot cast value to TIMESTAMP WITH TIME ZONE.', $diagnostic->message());
    }



}
