<?php

declare(strict_types=1);

namespace Tests\Semantic;

use Deriver\Result\Alternative;
use Deriver\Result\Exceptional;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;
use Tests\Fake\Programs\IterationPrograms;

/**
 * Checks live array traversal and reference binding during structural mutations.
 */
#[CoversNothing]
#[Small]
final class IterationSemanticsTest extends TestCase
{
    /**
     * @param string $source Trusted PHP fixture
     * @param string $normalJson Independently observed normal return values
     * @param string $exception Independently observed throwable class
     * @param bool $diagnostic Whether the target emits any diagnostic
     * @throws JsonException If fixture observations cannot be decoded
     */
    #[DataProviderExternal(IterationPrograms::class, 'cases')]
    public function testTargetIterationSemantics(string $source, string $normalJson, string $exception, bool $diagnostic): void
    {
        $expected = json_decode($normalJson, true, 512, JSON_THROW_ON_ERROR);
        $result = Analysis::returns($source);
        self::assertSame($expected, array_map(static fn (Alternative $outcome) => $outcome->values['return']->native(), $result->normalOutcomes));
        self::assertSame($exception === '' ? [] : [$exception], array_map(static fn (Exceptional $outcome): int|float|string|bool|null => $outcome->exception->literal, $result->exceptionalOutcomes));
        self::assertSame($diagnostic, in_array('PHP_WARNING', array_column($result->frontiers, 'code'), true));
        self::assertSame([], array_diff(array_column($result->frontiers, 'code'), ['PHP_WARNING']));
    }

    /**
     * PHP 8.3 skips the body of foreach over null or a scalar; a symbolic subject that may be null keeps both paths.
     * @param string $parameter Declared parameter of the iterated subject
     * @param list<string> $expected Possible returns
     * @param bool $warning Whether every path emits the non-iterable warning
     * @throws JsonException If fixture observations cannot be encoded
     */
    #[DataProvider('symbolicSubjects')]
    public function testSymbolicSubjectsKeepTheSkippedBody(string $parameter, array $expected, bool $warning): void
    {
        $result = Analysis::returns('<?php function target(' . $parameter . ' $rows){foreach($rows as $r){return "body";}return "skipped";}');
        $actual = [];
        foreach ($result->normalOutcomes as $outcome) {
            $value = $outcome->values['return']->native();
            if (!in_array($value, $actual, true)) {
                $actual[] = $value;
            }
        }
        sort($actual);
        self::assertSame($expected, $actual);
        self::assertSame($warning, in_array('foreach-non-iterable', array_column($result->frontiers, 'operation'), true));
    }

    /**
     * @return array<string, array{string, list<string>, bool}> Parameter declarations, possible returns, and warning presence
     */
    public static function symbolicSubjects(): array
    {
        return [
            'nullable array' => ['?array', ['body', 'skipped'], false],
            'untyped' => ['', ['body', 'skipped'], false],
            'scalar' => ['int|string', ['skipped'], true],
            'array' => ['array', ['body', 'skipped'], false],
        ];
    }
}
