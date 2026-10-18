<?php

declare (strict_types=1);

namespace Tests\Integration;

use Deriver\Deriver;
use Deriver\Deriver\Candidate;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluation;
use Deriver\Deriver\EvaluationOperation;
use Deriver\Deriver\Evaluator\Evidence\EvidenceKind;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Scalar\Identity;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Deriver\MicroEvaluator;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\SourceRef;
use Deriver\Exception\EvaluationContractException;
use Deriver\Exception\InvalidInputException;
use LogicException;
use org\bovigo\vfs\vfsStream;
use Override;
use phpmock\Mock;
use phpmock\MockBuilder;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use WeakMap;

#[CoversNothing]
#[Medium]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class MicroEvaluatorTest extends TestCase
{
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testEvaluatesOnlyDemandedArguments(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $sources = $filesystem;
        $name = 'target.php';
        $contents = '<?php function identity($value,$unused){return missing();} function target(){return strlen(identity("hello", expensive()));}';
        $url = $sources->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $sources->path() . '/' . $name;
        $evaluator = new MicroEvaluator('app.identity', '1', EvaluationOperation::Function, '\IDENTITY', static fn (Evaluation $evaluation): Term => $evaluation->input(0));
        $deriver = Deriver::prepare([$path], new DeriverConfig(evaluators: [$evaluator]));
        $result = $deriver->derive('target#return');
        self::assertSame(5, $result[0]->result);
        $frontiers = array_reduce(iterator_to_array($result, false), static function (array $frontiers, Candidate $candidate): array {
            if ($candidate->result instanceof Term) {
                $graph = $candidate->result;
                $snapshot = $candidate->evidence[0]->snapshot;
                $pending = [$graph];
                $seen = new WeakMap();
                $frontiers1 = [];
                while ($pending !== []) {
                    $node1 = array_pop($pending);
                    if (isset($seen[$node1])) {
                        continue;
                    }
                    $seen[$node1] = true;
                    array_push($pending, ...array_values($node1->operands));
                    $reason = $node1->attributes['reason'] ?? null;
                    if (!is_string($reason)) {
                        continue;
                    }
                    $source = new SourceRef($snapshot->id, (string) ($node1->attributes['source'] ?? ''), (int) ($node1->attributes['start'] ?? 0), (int) ($node1->attributes['end'] ?? $node1->attributes['start'] ?? 0));
                    $identity = (string) ($node1->attributes['identity'] ?? (new Identity())->key($node1));
                    $frontiers1[$identity] = new Frontier($reason, $source, (string) $node1->literal, ['value'], [$identity], $node1, $reason);
                }
                array_push($frontiers, ...array_values($frontiers1));
            }
            return $frontiers;
        }, []);
        self::assertSame([], $frontiers);
        self::assertSame([], $result->statistics->expandedBodies);
        $applications = array_values(array_filter($result[0]->evidence[0]->nodes(), static fn ($node): bool => $node->kind === EvidenceKind::EvaluatorApplication && $node->attributes['id'] === 'app.identity'));
        self::assertCount(1, $applications);
        self::assertCount(1, $applications[0]->inputs);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testUsesNamedArgumentsAndLeavesOtherArgumentsUnopened(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $sources = $filesystem;
        $name = 'target.php';
        $contents = '<?php function target(){return select(unused:missing(),value:21);}';
        $url = $sources->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $sources->path() . '/' . $name;
        $evaluator = new MicroEvaluator('app.select', '1', EvaluationOperation::Function, 'select', static fn (Evaluation $evaluation): Term => $evaluation->input('value'));
        $result = Deriver::prepare([$path], new DeriverConfig(evaluators: [$evaluator]))->derive('target#return');
        self::assertSame(21, $result[0]->result);
        $frontiers = array_reduce(iterator_to_array($result, false), static function (array $frontiers, Candidate $candidate): array {
            if ($candidate->result instanceof Term) {
                $graph = $candidate->result;
                $snapshot = $candidate->evidence[0]->snapshot;
                $pending = [$graph];
                $seen = new WeakMap();
                $frontiers1 = [];
                while ($pending !== []) {
                    $node = array_pop($pending);
                    if (isset($seen[$node])) {
                        continue;
                    }
                    $seen[$node] = true;
                    array_push($pending, ...array_values($node->operands));
                    $reason = $node->attributes['reason'] ?? null;
                    if (!is_string($reason)) {
                        continue;
                    }
                    $source = new SourceRef($snapshot->id, (string) ($node->attributes['source'] ?? ''), (int) ($node->attributes['start'] ?? 0), (int) ($node->attributes['end'] ?? $node->attributes['start'] ?? 0));
                    $identity = (string) ($node->attributes['identity'] ?? (new Identity())->key($node));
                    $frontiers1[$identity] = new Frontier($reason, $source, (string) $node->literal, ['value'], [$identity], $node, $reason);
                }
                array_push($frontiers, ...array_values($frontiers1));
            }
            return $frontiers;
        }, []);
        self::assertSame([], $frontiers);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testComposesOperandEvaluationWithBuiltInFunctions(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $sources = $filesystem;
        $name = 'target.php';
        $contents = '<?php function target(){return strlen("abc")+strlen("de");}';
        $url = $sources->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $sources->path() . '/' . $name;
        $evaluator = new MicroEvaluator('app.product', '1', EvaluationOperation::Binary, '+', static function (Evaluation $evaluation): Term {
            $left = $evaluation->input(0)->native();
            $right = $evaluation->input(1)->native();
            self::assertIsInt($left);
            self::assertIsInt($right);
            return Term::constant($left * $right);
        });
        self::assertSame(6, Deriver::prepare([$path], new DeriverConfig(evaluators: [$evaluator]))->derive('target#return')[0]->result);
        self::assertSame(5, Deriver::prepare([$path])->derive('target#return')[0]->result);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testConditionalEvaluationDemandsOnlyTheSelectedBranch(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $sources = $filesystem;
        $name = 'target.php';
        $contents = '<?php function target($flag){return $flag ? missing() : 42;}';
        $url = $sources->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $sources->path() . '/' . $name;
        $evaluator = new MicroEvaluator('app.false-branch', '1', EvaluationOperation::Conditional, '', static fn (Evaluation $evaluation): Term => $evaluation->input(1));
        $result = Deriver::prepare([$path], new DeriverConfig(evaluators: [$evaluator]))->derive('target#return');
        self::assertSame(42, $result[0]->result);
        $frontiers = array_reduce(iterator_to_array($result, false), static function (array $frontiers, Candidate $candidate): array {
            if ($candidate->result instanceof Term) {
                $graph = $candidate->result;
                $snapshot = $candidate->evidence[0]->snapshot;
                $pending = [$graph];
                $seen = new WeakMap();
                $frontiers1 = [];
                while ($pending !== []) {
                    $node = array_pop($pending);
                    if (isset($seen[$node])) {
                        continue;
                    }
                    $seen[$node] = true;
                    array_push($pending, ...array_values($node->operands));
                    $reason = $node->attributes['reason'] ?? null;
                    if (!is_string($reason)) {
                        continue;
                    }
                    $source = new SourceRef($snapshot->id, (string) ($node->attributes['source'] ?? ''), (int) ($node->attributes['start'] ?? 0), (int) ($node->attributes['end'] ?? $node->attributes['start'] ?? 0));
                    $identity = (string) ($node->attributes['identity'] ?? (new Identity())->key($node));
                    $frontiers1[$identity] = new Frontier($reason, $source, (string) $node->literal, ['value'], [$identity], $node, $reason);
                }
                array_push($frontiers, ...array_values($frontiers1));
            }
            return $frontiers;
        }, []);
        self::assertSame([], $frontiers);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testDecliningContinuesWithBuiltInAndSourceEvaluation(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $sources = $filesystem;
        $name = 'target.php';
        $contents = '<?php function helper(){return 5;} function target(){return strlen("abc")+helper();}';
        $url = $sources->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $sources->path() . '/' . $name;
        $evaluators = [new MicroEvaluator('app.strlen', '1', EvaluationOperation::Function, 'strlen', static fn (): ?Term => null), new MicroEvaluator('app.helper', '1', EvaluationOperation::Function, 'helper', static fn (): ?Term => null), new MicroEvaluator('app.plus', '1', EvaluationOperation::Binary, '+', static fn (): ?Term => null)];
        self::assertSame(8, Deriver::prepare([$path], new DeriverConfig(evaluators: $evaluators))->derive('target#return')[0]->result);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testPriorityAndEvidenceDoNotDependOnRegistrationOrder(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $sources = $filesystem;
        $name = 'target.php';
        $contents = '<?php function target(){return choose(99,42);}';
        $url = $sources->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $sources->path() . '/' . $name;
        $declines = 0;
        $declining = new MicroEvaluator('app.decline', '1', EvaluationOperation::Function, 'choose', static function (Evaluation $evaluation) use (&$declines): ?Term {
            $declines++;
            $evaluation->input(0);
            return null;
        }, priority: 10);
        $selected = new MicroEvaluator('app.selected', '1', EvaluationOperation::Function, 'choose', static fn (Evaluation $evaluation): Term => $evaluation->input(1));
        $first = Deriver::prepare([$path], new DeriverConfig(evaluators: [$declining, $selected]))->derive('target#return');
        $second = Deriver::prepare([$path], new DeriverConfig(evaluators: [$selected, $declining]))->derive('target#return');
        self::assertSame(42, $first[0]->result);
        self::assertSame(42, $second[0]->result);
        self::assertSame(2, $declines);
        $applications = array_values(array_filter($first[0]->evidence[0]->nodes(), static fn ($node): bool => $node->kind === EvidenceKind::EvaluatorApplication));
        self::assertCount(1, $applications);
        self::assertSame('app.selected', $applications[0]->attributes['id']);
        self::assertCount(1, $applications[0]->inputs);
        self::assertSame($first[0]->evidence[0]->snapshot->id, $second[0]->evidence[0]->snapshot->id);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testCanReturnAPartialExpressionWithItsDemandedInput(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $sources = $filesystem;
        $name = 'target.php';
        $contents = '<?php function target($value){return external($value);}';
        $url = $sources->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $sources->path() . '/' . $name;
        $evaluator = new MicroEvaluator('app.external', '1', EvaluationOperation::Function, 'external', static fn (Evaluation $evaluation): Term => new Term(TermKind::Operation, 'external-result', [$evaluation->input(0)]));
        $result = Deriver::prepare([$path], new DeriverConfig(evaluators: [$evaluator]))->derive('target#return');
        self::assertInstanceOf(Term::class, $result[0]->result);
        self::assertSame('external-result', $result[0]->result->literal);
        self::assertNotEmpty($result[0]->result->operands);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testEvaluationStateIsFreshForEachQuery(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $sources = $filesystem;
        $name = 'target.php';
        $contents = '<?php function target(){return first(second());}';
        $url = $sources->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $sources->path() . '/' . $name;
        $calls = 0;
        $evaluators = [new MicroEvaluator('app.first', '1', EvaluationOperation::Function, 'first', static function (Evaluation $evaluation): Term {
            $evaluation->input(0);
            return $evaluation->input(0);
        }), new MicroEvaluator('app.second', '1', EvaluationOperation::Function, 'second', static function () use (&$calls): Term {
            $calls++;
            return Term::constant(7);
        })];
        $deriver = Deriver::prepare([$path], new DeriverConfig(evaluators: $evaluators));
        self::assertSame(7, $deriver->derive('target#return')[0]->result);
        self::assertSame(1, $calls);
        self::assertSame(7, $deriver->derive('target#return')[0]->result);
        self::assertSame(2, $calls);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testDisabledStandardEvaluatorsDoNotDisableConfiguredEvaluators(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $sources = $filesystem;
        $name = 'target.php';
        $contents = '<?php function target(){return answer();}';
        $url = $sources->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $sources->path() . '/' . $name;
        $evaluator = new MicroEvaluator('app.answer', '1', EvaluationOperation::Function, 'answer', static fn (): Term => Term::constant(42));
        self::assertSame(42, Deriver::prepare([$path], new DeriverConfig(standardEvaluators: false, evaluators: [$evaluator]))->derive('target#return')[0]->result);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testInvalidInputDemandReportsTheEvaluatorIdentity(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $sources = $filesystem;
        $name = 'target.php';
        $contents = '<?php function target(){return answer();}';
        $url = $sources->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $sources->path() . '/' . $name;
        $evaluator = new MicroEvaluator('app.invalid', '1', EvaluationOperation::Function, 'answer', static fn (Evaluation $evaluation): Term => $evaluation->input(3));
        $deriver = Deriver::prepare([$path], new DeriverConfig(evaluators: [$evaluator]));
        $this->expectException(EvaluationContractException::class);
        $this->expectExceptionMessage('app.invalid: The micro evaluator requested an absent input: 3');
        $deriver->derive('target#return');
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testCallbackFailureReportsTheEvaluatorIdentity(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $sources = $filesystem;
        $name = 'target.php';
        $contents = '<?php function target(){return answer();}';
        $url = $sources->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $sources->path() . '/' . $name;
        $cause = new LogicException('broken evaluation');
        $evaluator = new MicroEvaluator('app.broken', '1', EvaluationOperation::Function, 'answer', static fn (): Term => throw $cause);
        $deriver = Deriver::prepare([$path], new DeriverConfig(evaluators: [$evaluator]));
        $this->expectException(EvaluationContractException::class);
        $this->expectExceptionMessage('app.broken: broken evaluation');
        $deriver->derive('target#return');
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testRejectsConflictingMicroEvaluatorsDuringPreparation(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $evaluators = [new MicroEvaluator('app.first', '1', EvaluationOperation::Function, 'answer', static fn (): Term => Term::constant(1)), new MicroEvaluator('app.second', '1', EvaluationOperation::Function, '\ANSWER', static fn (): Term => Term::constant(2))];
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('EVALUATOR_CONFLICT');
        Deriver::prepare([], new DeriverConfig(evaluators: $evaluators));
    }
    #[Override]
    protected function tearDown(): void
    {
        Mock::disableAll();
        parent::tearDown();
    }
}
