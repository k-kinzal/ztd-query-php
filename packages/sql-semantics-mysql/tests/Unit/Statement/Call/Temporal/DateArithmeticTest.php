<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Temporal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\DateArithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(DateArithmetic::class)]
#[Small]
final class DateArithmeticTest extends TestCase
{
    public function testDeriveScalarFollowsTheDateAndTheUnit(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $derivation = new Derivation($platform->context($profile, null, [], false));
        $fact = $derivation->scalar(new DateArithmetic(true, new TemporalLiteral(TemporalForm::Date, '2024-01-01'), new NumberLiteral('1'), IntervalUnit::Second), $derivation->environment());

        self::assertEquals(new Known(new Domain(Kind::DateTime, Field::DateTime, 19)), $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testRenderWritesDateSub(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new DateArithmetic(true, new ColumnUse(new Name('a')), new NumberLiteral('1'), IntervalUnit::Second))->render($out);

        self::assertSame('DATE_SUB(a, INTERVAL 1 SECOND)', (new Lexical())->join($out->pieces()));
    }
    /**
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, string}>
     */
    public static function providerWrittenForms(): iterable
    {
        foreach (\SqlSemantics\Contract\GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            foreach (["ADDDATE('2020-01-01', INTERVAL 1 DAY)", "SUBDATE('2020-01-01', INTERVAL 1 DAY)", "DATE_ADD('2020-01-01', INTERVAL 1 DAY)", "DATE_SUB('2020-01-01', INTERVAL 1 DAY)"] as $expression) {
                foreach (['', ' AS chosen'] as $alias) {
                    yield $release->value . '/statement/' . $expression . $alias => [$release, 'SELECT ' . $expression . $alias];
                    yield $release->value . '/program/' . $expression . $alias => [$release, 'CREATE PROCEDURE wrapper() SELECT ' . $expression . $alias];
                }
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerWrittenForms')]
    public function testRenderPreservesTheStructureBeforeOutputSpelling(\SqlSemantics\Contract\GrammarRelease $release, string $sql): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, $release->value);
        $formatter = new \SqlFormatter\Facade\Formatter($semantics->parser(), new \SqlFormatter\Core\FormatOptions(\SqlFormatter\Core\Style::Compact));
        $statement = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($semantics->context(), $semantics->analyze($sql));
        $information = $statement->statementInformation();
        $out = new Output(new \SqlSemantics\Platform\MySql\Rendering\Codec($release, $information->profile->lexical, $information->client));
        $statement->render($out);
        self::assertSame($formatter->format($sql), $formatter->format((new Lexical())->join($out->canonical())));
        self::assertSame($formatter->format($sql), $formatter->format($statement->toString()));
    }
}
