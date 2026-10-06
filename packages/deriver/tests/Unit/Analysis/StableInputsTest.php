<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use Deriver\Analysis\StableInputs;
use Deriver\ControlFlow\Instruction;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class StableInputsTest extends TestCase
{
    public function testRecoverDoesNotTreatScriptGlobalsAsImmutableParameters(): void
    {
        $body = \Tests\Fake\SolverFixture::context()->program->callable('script:fixture.php');
        self::assertNotNull($body);
        self::assertSame([], (new StableInputs())->recover($body, [], 'TIME_LIMIT'));
    }

    public function testRecoverRejectsCatchBindingsEvenWithoutExplicitWrites(): void
    {
        $body = \Tests\Fake\SolverFixture::context('<?php function target(PDO $pdo){try{}catch(Throwable $pdo){}}')->program->callable('target');
        self::assertNotNull($body);
        self::assertSame([], (new StableInputs())->recover($body, [], 'TIME_LIMIT'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerExtractNames')]
    public function testSymbolEffectRecognizesDynamicAndQualifiedExtractCalls(string $name): void
    {
        $at = new SourceRef('test', 'a.php', 0, 1);
        $call = new Instruction('call', 'invoke', $at, 'result', ['name']);
        $proof = new StableInputs();
        self::assertTrue($proof->symbolEffect($call, []));
        self::assertTrue($proof->symbolEffect($call, ['name' => new Instruction('name', 'constant', $at, 'name', constant: Term::constant($name))]));
        self::assertFalse($proof->symbolEffect($call, ['name' => new Instruction('name', 'constant', $at, 'name', constant: Term::constant('heavy'))]));
    }
    /**
     * @return list<array{string}>
     */
    public static function providerExtractNames(): array
    {
        return [['extract'], ['\\extract'], ['App\\extract'], ['EXTRACT']];
    }

    public function testTypeIncludesImplicitlyNullableDefaults(): void
    {
        $body = \Tests\Fake\SolverFixture::context('<?php function target(PDO $pdo=null){}')->program->callable('target');
        self::assertNotNull($body);
        self::assertSame('PDO|null', (new StableInputs())->type($body->parameters[0]));
    }

}
