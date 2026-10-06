<?php

declare(strict_types=1);

namespace Tests\ModelContract;

use Deriver\Analyzer;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Signature\Parameter;
use Deriver\Model\Signature\Signature;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\ReturnQuery;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;
use Tests\Fake\PlanModel;

/**
 * Exercises model locations and ordered actions through the public analysis API.
 */
#[CoversNothing]
#[Small]
final class DeclarationContractTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSignatureOnlyDeclarationsDoNotBehaveAsEmptyBodies(): void
    {
        $session = (new Analyzer())->open(new ProjectInput([new SourceFile('app.php', '<?php function target(){$x=1;external($x);return $x;}'), new SourceFile('stubs.php', '<?php function external(int &$value): int {return 99;}', true)]));
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame('call-write', $result->candidates[0]->term->kind);
        self::assertSame(1, $result->candidates[0]->term->operands[0]->native());
        self::assertContains('MISSING_SOURCE', array_column(\Tests\Semantic\CandidateContractTest::frontiers($result), 'code'));
        self::assertSame('partials', $result->candidates[0]->type);
        self::assertSame(['stubs.php' => 'declarations'], $session->snapshot()->sourceModes);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testDeclaredArgumentErrorsOccurBeforeTheUnavailableBody(): void
    {
        $session = (new Analyzer())->open(new ProjectInput([new SourceFile('app.php', '<?php function target(){try{return external(value: []);}catch(TypeError $e){return "typed";}}'), new SourceFile('stubs.php', '<?php function external(int $value): int {}', true)]));
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame('typed', $result->candidates[0]->term->native());
        self::assertSame([], \Tests\Semantic\CandidateContractTest::frontiers($result));
        self::assertSame([], array_filter($result->candidates, static fn ($candidate): bool => $candidate->type_name === 'never'));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testModelsSupplySemanticsForExternalDeclarationsWithoutReplacingSourceBodies(): void
    {
        $model = new PlanModel(new ModelDescriptor('example.external', '1', 'external', new Signature([new Parameter('value', 'int')], 'int')), new SemanticPlan([Action::returns(Expression::binary('+', Expression::parameter('value'), Expression::literal(Term::constant(1))))]));
        $session = (new Analyzer())->open(new ProjectInput([new SourceFile('app.php', '<?php function target(){return external(value: 2);}'), new SourceFile('stubs.php', '<?php function external(int $value): int {}', true)]), new Configuration(models: [$model]));
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame(3, $result->candidates[0]->term->native());
        self::assertSame([], \Tests\Semantic\CandidateContractTest::frontiers($result));
        self::assertSame([], array_filter($result->candidates, static fn ($candidate): bool => $candidate->type_name === 'never'));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSourceModeChangesInvalidateLoweringAndSnapshotCaches(): void
    {
        $analyzer = new Analyzer();
        $stub = $analyzer->open(new ProjectInput([new SourceFile('library.php', '<?php function target(){return 7;}', true)]));
        $source = $analyzer->open(new ProjectInput([new SourceFile('library.php', '<?php function target(){return 7;}')]));
        self::assertNotSame($stub->snapshot()->id, $source->snapshot()->id);
        self::assertNotEmpty(\Tests\Semantic\CandidateContractTest::frontiers($stub->derive(new ReturnQuery('target'))));
        self::assertSame(7, $source->derive(new ReturnQuery('target'))->candidates[0]->term->native());
        self::assertSame([], \Tests\Semantic\CandidateContractTest::frontiers($source->derive(new ReturnQuery('target'))));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testExternalDefaultsAreEvaluatedPerInvocation(): void
    {
        $session = new \Deriver\Analysis\ExecutionSession(new ProjectInput([new SourceFile('app.php', '<?php class Token{function __construct(){throw new RuntimeException();}}function target(){try{external();}catch(RuntimeException $e){return 1;}}'), new SourceFile('stubs.php', '<?php function external($value = new Token) {}', true)]), new Configuration());
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame(1, $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testExternalClassAccessStillUsesSourceVisibility(): void
    {
        $session = (new Analyzer())->open(new ProjectInput([new SourceFile('app.php', '<?php function target(){try{return Box::privateMethod();}catch(Error $e){return "private";}}'), new SourceFile('stubs.php', '<?php class Box{private static function privateMethod(){}}', true)]));
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame('private', $result->candidates[0]->term->native());
        self::assertSame([], \Tests\Semantic\CandidateContractTest::frontiers($result));
        self::assertSame([], array_filter($result->candidates, static fn ($candidate): bool => $candidate->type_name === 'never'));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testModeledMethodReturnsResolveSelfInItsDeclaredClass(): void
    {
        $model = new PlanModel(new ModelDescriptor('example.same', '1', 'Box::same', new Signature(returnType: 'self')), new SemanticPlan([Action::returns(Expression::receiver())]));
        $session = (new Analyzer())->open(new ProjectInput([new SourceFile('app.php', '<?php class Box{}function target(){$box=new Box;$same=$box->same();return $same===$box;}')]), new Configuration(models: [$model]));
        $result = $session->derive(new ReturnQuery('target'));
        self::assertTrue($result->candidates[0]->term->native());
        self::assertSame([], \Tests\Semantic\CandidateContractTest::frontiers($result));
        self::assertSame([], array_filter($result->candidates, static fn ($candidate): bool => $candidate->type_name === 'never'));
    }
}
