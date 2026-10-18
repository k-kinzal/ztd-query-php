<?php

declare(strict_types=1);

namespace Tests\Integration;

use Deriver\Deriver\Candidate\Snapshot;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Publication\CandidateQuery;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Sources\Source;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class PreparedDependenciesTest extends TestCase
{
    public function testCapturedInputsDoNotCompileUnrelatedFunctionsAndQueriesReuseTheirGraphs(): void
    {
        $php = '<?php function factory(){$value=7; return fn()=>$value+1;}' . implode('', array_map(static fn (int $index): string => ' function unrelated' . $index . '(){return 99;}', range(0, 199)));
        $source = new Source('app.php', $php);
        $program = new Index('prepared', [(new Resolver())->resolve((new Parser(new ParserConfig('8.3')))->parse($source))]);
        $closures = array_values(array_filter($program->symbols(), static fn (string $symbol): bool => str_starts_with($symbol, 'closure:')));
        self::assertCount(1, $closures);
        $before = $program->graphCount();
        $target = new TargetProfile();
        $queries = new CandidateQuery(new DefinitionIndex($program), new EvaluatorConfig($target), new Evaluators($target), new Snapshot('prepared', [], [], $target, 'environment'));
        $first = $queries->derive(new ReturnQuery($closures[0]));
        $count = $program->graphCount();
        $second = $queries->derive(new ReturnQuery($closures[0]));
        self::assertSame(0, $before);
        self::assertSame(8, $first[0]->result);
        self::assertSame(8, $second[0]->result);
        self::assertSame(2, $count);
        self::assertSame($count, $program->graphCount());
        self::assertSame($first[0]->evidence[0]->root->id, $second[0]->evidence[0]->root->id);
    }

    public function testGlobalInputsCompileOnlyScriptsWhichCanSupplyTheRequestedName(): void
    {
        $sources = [new Source('app.php', '<?php function target(){global $wanted; return $wanted;}'), new Source('values.php', '<?php $wanted=7;'), ...array_map(static fn (int $index): Source => new Source('other' . $index . '.php', '<?php $unrelated=99;'), range(0, 99))];
        $parser = new Parser(new ParserConfig('8.3'));
        $program = new Index('prepared', array_map(static fn (Source $source) => (new Resolver())->resolve($parser->parse($source)), $sources));
        $target = new TargetProfile();
        $queries = new CandidateQuery(new DefinitionIndex($program), new EvaluatorConfig($target), new Evaluators($target), new Snapshot('prepared', [], [], $target, 'environment'));
        $result = $queries->derive(new ReturnQuery('target'));
        self::assertSame(7, $result[0]->result);
        self::assertSame(2, $program->graphCount());
    }

}
