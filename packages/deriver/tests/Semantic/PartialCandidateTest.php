<?php

declare(strict_types=1);

namespace Tests\Semantic;

use Deriver\Query\ValueQuery;
use Deriver\Result\Alternative;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;
use Tests\Fake\Candidates;

/**
 * Known parts of arrays with unpacked unknown sources and of dynamic formats stay available as candidates.
 */
#[CoversNothing]
#[Medium]
final class PartialCandidateTest extends TestCase
{
    /**
     * PHP 8.3 appends every unpacked entry after the existing integer keys, so they are never replaced.
     * @param string $body Function body using $cols = ["id", "name", ...$x]
     * @param list<mixed> $expected Every candidate of the sink argument
     * @throws JsonException If captured fixture values cannot be encoded
     */
    #[DataProvider('knownHeads')]
    public function testKnownHeadOfAnUnpackedUnknownArrayIsExact(string $body, array $expected): void
    {
        $result = Candidates::sink('function target(array $x){$cols=["id","name",...$x];' . $body . '}');
        self::assertSame($expected, array_values(array_unique(array_map(static fn (Alternative $outcome) => $outcome->values['value']->native(), $result->normalOutcomes), SORT_REGULAR)));
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }

    /**
     * @return array<string, array{string, list<mixed>}> Independent source fixtures and PHP outcomes
     */
    public static function knownHeads(): array
    {
        return [
            'first read' => ['sink($cols[0]);', ['id']],
            'string key read' => ['sink($cols["1"]);', ['name']],
            'destructuring' => ['[$a,$b]=$cols;sink($a.",".$b);', ['id,name']],
            'first iteration' => ['foreach($cols as $k=>$v){sink("$k=$v");break;}', ['0=id']],
            'second iteration' => ['$i=0;foreach($cols as $k=>$v){if($i++===1){sink("$k=$v");break;}}', ['1=name']],
            'strict membership' => ['sink(in_array("name",$cols,true));', [true]],
            'key presence' => ['sink(array_key_exists(1,$cols));', [true]],
            'array_merge read' => ['sink(array_merge(["id"],$x)[0]);', ['id']],
        ];
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testReadsBeyondTheKnownHeadStaySymbolic(): void
    {
        $result = Candidates::sink('function target(array $x){$cols=["id","name",...$x];sink($cols[2]);}');
        self::assertNotEmpty($result->normalOutcomes);
        foreach ($result->normalOutcomes as $outcome) {
            self::assertFalse($outcome->values['value']->isConcrete());
        }
    }

    /**
     * implode(",", ["id","name",...$x]) is "id,name" for an empty $x and starts with "id,name," otherwise.
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testImplodeOfAnUnpackedUnknownArrayKeepsBothShapes(): void
    {
        $result = Candidates::sink('function target(array $x){sink(implode(",",["id","name",...$x]));}');
        $values = array_map(static fn (Alternative $outcome): Term => $outcome->values['value'], $result->normalOutcomes);
        self::assertCount(2, $values);
        self::assertSame('id,name', $values[0]->native());
        self::assertSame('id,name,', $values[1]->operands[0]->native());
        self::assertFalse($values[1]->isConcrete());
        self::assertSame(['UNSUPPORTED_MODEL_CASE'], array_column($result->frontiers, 'code'));
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testUnpackingIntoAnOrdinaryArrayCannotFail(): void
    {
        $result = Candidates::sink('function target(array $x){try{$cols=["id",...$x];sink("built");}catch(Error $e){sink("failed");}}');
        self::assertSame(['built'], array_map(static fn (Alternative $outcome) => $outcome->values['value']->native(), $result->normalOutcomes));
        self::assertSame([], $result->frontiers);
    }

    /**
     * The format is processed left to right, so completed specifiers before the unknown text are already formatted.
     * @param string $call Formatting call
     * @param string $prefix Known start of every normal result
     * @throws JsonException If captured fixture values cannot be encoded
     */
    #[DataProvider('dynamicFormats')]
    public function testDynamicFormatKeepsItsFormattedKnownPrefix(string $call, string $prefix): void
    {
        $result = Candidates::sink('function target($f,string $s){sink(' . $call . ');}');
        self::assertNotEmpty($result->normalOutcomes);
        foreach ($result->normalOutcomes as $outcome) {
            $value = $outcome->values['value'];
            self::assertSame('concat', $value->kind);
            self::assertSame($prefix, $value->operands[0]->native());
        }
        self::assertContains('sprintf', array_column($result->frontiers, 'operation'));
    }

    /**
     * @return array<string, array{string, string}> Independent source fixtures and PHP outcomes
     */
    public static function dynamicFormats(): array
    {
        return [
            'mixed suffix' => ['sprintf("SELECT %s FROM t WHERE ".$f,"id")', 'SELECT id FROM t WHERE '],
            'string suffix' => ['sprintf("SELECT %s, %d FROM t ".$s,"id","7")', 'SELECT id, 7 FROM t '],
            'trailing percent' => ['sprintf("100%".$s,"a")', '100'],
            'trailing argument number' => ['sprintf(\'%1$s %2\'.$s,"a","b")', 'a '],
        ];
    }

    /**
     * @param string $call Formatting call with a literal format
     * @param string $expected PHP 8.3 result or exception class
     * @throws JsonException If captured fixture values cannot be encoded
     */
    #[DataProvider('literalFormats')]
    public function testLiteralFormatsFollowTargetArgumentRules(string $call, string $expected): void
    {
        $session = Analysis::session('<?php function sink($value){} function target(){try{sink(' . $call . ');}catch(ValueError $e){sink("ValueError");}catch(ArgumentCountError $e){sink("ArgumentCountError");}}');
        $values = [];
        foreach ($session->callsTo('sink') as $site) {
            foreach ($session->derive(new ValueQuery($site->argument(0)))->normalOutcomes as $outcome) {
                $values[] = $outcome->values['value']->native();
            }
        }
        self::assertSame([$expected], $values);
    }

    /**
     * @return array<string, array{string, string}> Independent source fixtures and PHP outcomes
     */
    public static function literalFormats(): array
    {
        return [
            'vsprintf uses values in order' => ['vsprintf("%s",["a"=>"x"])', 'x'],
            'vsprintf missing item' => ['vsprintf("%s %s",["x"])', 'ValueError'],
            'zero argument number' => ['sprintf(\'%0$s\',"x")', 'ValueError'],
            'numbered percent needs an argument' => ['sprintf(\'%1$%\')', 'ArgumentCountError'],
            'invalid number after a missing argument' => ['sprintf(\'%s %0$s\')', 'ValueError'],
        ];
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testIterationOfAnUnknownArrayNeverYieldsItsOperands(): void
    {
        $result = Candidates::sink('function target(string $s){foreach(explode(",",$s) as $part){sink($part);break;}}');
        foreach ($result->normalOutcomes as $outcome) {
            self::assertFalse($outcome->values['value']->isConcrete());
        }
    }
}
