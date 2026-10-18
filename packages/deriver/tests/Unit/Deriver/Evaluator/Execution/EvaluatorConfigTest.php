<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Execution;

use Deriver\Deriver\Evaluator\Execution\EvaluatorConfig as Subject;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class EvaluatorConfigTest extends TestCase
{
    public function testPreservesExplicitSemanticInputs(): void
    {
        $target = new TargetProfile(version: '8.4', floatPrecision: 14);
        $input = Term::parameter('db', 'DB');
        $config = new Subject(target: $target, environment: ['$db' => $input]);
        self::assertSame($target, $config->target);
        self::assertSame($input, $config->environment['$db']);
    }
}
