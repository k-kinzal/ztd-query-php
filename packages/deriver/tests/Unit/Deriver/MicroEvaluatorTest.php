<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver;

use Deriver\Deriver\Evaluation;
use Deriver\Deriver\EvaluationOperation;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\MicroEvaluator;
use Deriver\Deriver\Sources\SourceRef;
use Deriver\Exception\InvalidInputException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class MicroEvaluatorTest extends TestCase
{
    public function testEvaluateUsesThePublicLazyInputContract(): void
    {
        $seen = [];
        $evaluation = new Evaluation(EvaluationOperation::Function, 'identity', new SourceRef('s', 'a.php', 0, 10), static function (int|string $key) use (&$seen): Term {
            $seen[] = $key;
            return Term::constant(21);
        });
        $evaluator = new MicroEvaluator('app.identity', '1', EvaluationOperation::Function, '\\IDENTITY', static fn (Evaluation $input): Term => $input->input(0));
        self::assertSame('identity', $evaluator->name);
        self::assertSame(21, $evaluator->evaluate($evaluation)?->native());
        self::assertSame([0], $seen);
    }

    #[DataProvider('providerInvalidDefinitions')]
    public function testRejectsInvalidDefinitions(string $id, string $version, string $name, ?int $start, ?int $end): void
    {
        $this->expectException(InvalidInputException::class);
        new MicroEvaluator($id, $version, EvaluationOperation::Function, $name, static fn (): ?Term => null, start: $start, end: $end);
    }

    /**
     * @return iterable<string, array{string, string, string, ?int, ?int}>
     */
    public static function providerInvalidDefinitions(): iterable
    {
        yield 'missing identity' => ['', '1', 'f', null, null];
        yield 'missing version' => ['f', '', 'f', null, null];
        yield 'empty function' => ['f', '1', '\\', null, null];
        yield 'negative start' => ['f', '1', 'f', -1, 3];
        yield 'reversed range' => ['f', '1', 'f', 4, 3];
    }
}
