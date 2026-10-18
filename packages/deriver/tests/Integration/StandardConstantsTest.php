<?php

declare(strict_types=1);

namespace Tests\Integration;

use Deriver\Deriver\Candidate\Snapshot;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Publication\CandidateQuery;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Sources\Source;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class StandardConstantsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function providerConstants(): iterable
    {
        yield 'literal case and qualification' => ['namespace C; function subject(){return [TRUE, false, NuLl, \\true, \\FALSE, \\NULL];}', [true,false,null,true,false,null]];
        yield 'global standard fallback' => ['namespace C; function subject(){return [PHP_INT_SIZE, PHP_INT_MIN, PHP_INT_MAX];}', [8,-9223372036854775807 - 1,9223372036854775807]];
        yield 'namespace declaration precedes fallback' => ['namespace C; const PHP_INT_SIZE=17; function subject(){return [PHP_INT_SIZE, \\PHP_INT_SIZE];}', [17,8]];
        yield 'global declaration fallback' => ['namespace {const VALUE=17;} namespace C {function subject(){return VALUE;}}', 17];
        yield 'defined constant fallback' => ['namespace {define("VALUE",17);} namespace C {function subject(){return VALUE;}}', 17];
        yield 'defined namespace constant precedes fallback' => ['namespace {define("VALUE",4); define("C\\\\VALUE",17);} namespace C {function subject(){return VALUE;}}', 17];
        yield 'imported declaration precedes standard spelling' => ['namespace D {const VALUE=17;} namespace C {use const D\\VALUE as PHP_INT_SIZE; function subject(){return PHP_INT_SIZE;}}', 17];
    }

    #[DataProvider('providerConstants')]
    public function testResolvesConstantsWithoutUsingHostDefinitions(string $source, mixed $expected): void
    {
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('constants.php', '<?php ' . $source)));
        $program = new Index('constants', [$tree]);
        $target = new TargetProfile();
        $query = new CandidateQuery(new DefinitionIndex($program), new EvaluatorConfig($target), new Evaluators($target), new Snapshot('constants', [], [], $target, 'environment'));
        $result = $query->derive(new ReturnQuery('C\\subject'));
        self::assertCount(1, $result);
        self::assertSame($expected, $result[0]->result);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerUnresolved(): iterable
    {
        yield 'qualified name has no global fallback' => ['namespace\\PHP_INT_SIZE'];
        yield 'standard names are case sensitive' => ['php_int_size'];
        yield 'host-specific constants are not imported' => ['PHP_BINARY'];
    }

    #[DataProvider('providerUnresolved')]
    public function testDoesNotInventAValueForAnUnresolvedConstant(string $expression): void
    {
        $source = 'namespace C; function subject(){return ' . $expression . ';}';
        $tree = (new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse(new Source('constants.php', '<?php ' . $source)));
        $program = new Index('constants', [$tree]);
        $target = new TargetProfile();
        $query = new CandidateQuery(new DefinitionIndex($program), new EvaluatorConfig($target), new Evaluators($target), new Snapshot('constants', [], [], $target, 'environment'));
        $result = $query->derive(new ReturnQuery('C\\subject'));
        self::assertCount(1, $result);
        self::assertInstanceOf(Term::class, $result[0]->result);
        self::assertSame('MISSING_CONSTANT', $result[0]->result->attributes['reason']);
    }

}
