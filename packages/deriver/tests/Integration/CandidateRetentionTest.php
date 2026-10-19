<?php

declare (strict_types=1);

namespace Tests\Integration;

use Deriver\Deriver;
use Deriver\Deriver\BranchEvaluators;
use Deriver\Deriver\Candidate;
use Deriver\Deriver\Candidate\CandidateStatus;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Deriver\Sources\Path;
use LogicException;
use org\bovigo\vfs\vfsStream;
use Override;
use phpmock\MockBuilder;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Medium]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CandidateRetentionTest extends TestCase
{
    /**
     * @throws \phpmock\MockEnabledException
     */
    #[Override]
    protected function setUp(): void
    {
        $workingDirectory = '/project';
        vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use ($workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use ($workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use ($workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use ($workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
    }

    public function testBinaryOperatorsCombineACallWithAVariableRead(): void
    {
        file_put_contents(vfsStream::url('project') . '/app.php', '<?php function text($s){} function number($n){} function up($v){return strtoupper($v);}
            class Model{public $pk="k";public function g(){return "g";}}
            function literal(){$a="x";text(strtoupper($a)." - ".$a);}
            function twoVariables(){$a="x";$b="y";text($b.up($a));}
            function arithmetic(){$a="xy";number(strlen($a)+strlen($a));}
            function property(){$o=new Model();text($o->g().$o->pk);}
            function symbolic($a){return strtoupper($a).$a;}');
        $deriver = Deriver::prepare(['/project/app.php']);
        $texts = array_map(static fn (Candidate $candidate): string|int|null => is_string($candidate->result) || is_int($candidate->result) || $candidate->result === null ? $candidate->result : throw new LogicException('Expected a scalar candidate.'), iterator_to_array($deriver->derive('text#parameter(s)'), false));
        sort($texts);
        self::assertSame(['X - x', 'gk', 'yX'], $texts);
        self::assertSame([4], array_map(static fn (Candidate $candidate): string|int|null => is_string($candidate->result) || is_int($candidate->result) || $candidate->result === null ? $candidate->result : throw new LogicException('Expected a scalar candidate.'), iterator_to_array($deriver->derive('number#parameter(n)'), false)));
        $symbolic = $deriver->derive('symbolic#return');
        self::assertCount(1, $symbolic);
        self::assertSame(CandidateStatus::Partials, $symbolic[0]->type);
    }

    public function testGroupedCaseLabelsKeepTheSharedAssignment(): void
    {
        file_put_contents(vfsStream::url('project') . '/app.php', '<?php function sink($s){} function run($type){switch($type){case "post":case "page":$o="title";break;default:$o="id";}sink($o);}');
        $path = '/project/app.php';
        $structural = array_map(static fn (Candidate $candidate): string|int|null => is_string($candidate->result) || is_int($candidate->result) || $candidate->result === null ? $candidate->result : throw new LogicException('Expected a scalar candidate.'), iterator_to_array(Deriver::prepare([$path])->derive('sink#parameter(s)'), false));
        sort($structural);
        self::assertSame(['id', 'title'], $structural);
        $pruned = array_map(static fn (Candidate $candidate): string|int|null => is_string($candidate->result) || is_int($candidate->result) || $candidate->result === null ? $candidate->result : throw new LogicException('Expected a scalar candidate.'), iterator_to_array(Deriver::prepare([$path], new DeriverConfig(evaluators: BranchEvaluators::truth()))->derive('sink#parameter(s)'), false));
        sort($pruned);
        self::assertSame(['id', 'title'], $pruned);
    }

    public function testReceiversWithoutAClassKeepTheArgumentBehindAnUnresolvedDispatch(): void
    {
        file_put_contents(vfsStream::url('project') . '/app.php', '<?php class DB{public function query($sql){}}
            function untyped($db){$db->query("UNTYPED");}
            class Holder{public $db;function run(){$this->db->query("PROPERTY");}}
            function nullsafe(?DB $db){$db?->query("NULLSAFE");}
            function never(){global $never;$never->query("GLOBAL");}
            function typed(DB $db){$db->query("TYPED");}');
        $candidates = iterator_to_array(Deriver::prepare(['/project/app.php'])->derive('DB::query#parameter(sql)'), false);
        $partials = array_map(static fn (Candidate $candidate): Term => $candidate->result instanceof Term ? $candidate->result : throw new LogicException('Expected a partial candidate.'), array_values(array_filter($candidates, static fn (Candidate $candidate): bool => $candidate->result instanceof Term)));
        $analyzed = array_map(static fn (Candidate $candidate): string|int|null => is_string($candidate->result) || is_int($candidate->result) || $candidate->result === null ? $candidate->result : throw new LogicException('Expected a scalar candidate.'), array_values(array_filter($candidates, static fn (Candidate $candidate): bool => !$candidate->result instanceof Term)));
        sort($analyzed);
        self::assertSame(['NULLSAFE', 'TYPED'], $analyzed);
        self::assertSame([TermKind::ControlledValue, TermKind::ControlledValue, TermKind::ControlledValue], array_map(static fn (Term $partial): TermKind => $partial->kind, $partials));
        self::assertSame(['UNRESOLVED_DISPATCH', 'UNRESOLVED_DISPATCH', 'UNRESOLVED_DISPATCH'], array_map(static fn (Term $partial): string|int|float|bool|null => $partial->attributes['reason'] ?? null, $partials));
        $arguments = array_map(static fn (Term $partial): string|int|float|bool|null => $partial->operands[0]->literal, $partials);
        sort($arguments);
        self::assertSame(['GLOBAL', 'PROPERTY', 'UNTYPED'], $arguments);
    }

    public function testExplicitConstructorCallsAreConstructorCallers(): void
    {
        file_put_contents(vfsStream::url('project') . '/app.php', '<?php class Base{public function __construct($name){}} class Child extends Base{public function __construct(){parent::__construct("child");}} class Other extends Base{public static function make(){return new static("made");}} function direct(){new Base("direct");}');
        $names = array_map(static fn (Candidate $candidate): string|int|null => is_string($candidate->result) || is_int($candidate->result) || $candidate->result === null ? $candidate->result : throw new LogicException('Expected a scalar candidate.'), iterator_to_array(Deriver::prepare(['/project/app.php'])->derive('Base::__construct#parameter(name)'), false));
        sort($names);
        self::assertSame(['child', 'direct', 'made'], $names);
    }

    public function testEveryCallThroughAGlobalReceiverIsObserved(): void
    {
        file_put_contents(vfsStream::url('project') . '/wpdb.php', '<?php class wpdb{public function get_var($query){return 1;} public function chain($query){return $this;}} $wpdb=new wpdb(); $wpdb->chain("FIRST")->chain("CHAINED");$wpdb->chain("SECOND");');
        file_put_contents(vfsStream::url('project') . '/app.php', '<?php function f(){global $wpdb;$wpdb->get_var("A");$r=$wpdb->get_var("B");$wpdb->get_var("C".$r);}');
        $deriver = Deriver::prepare(['/project/wpdb.php', '/project/app.php']);
        $queries = array_map(static fn (Candidate $candidate): string|int|null => is_string($candidate->result) || is_int($candidate->result) || $candidate->result === null ? $candidate->result : throw new LogicException('Expected a scalar candidate.'), iterator_to_array($deriver->derive('wpdb::get_var#parameter(query)'), false));
        sort($queries);
        self::assertSame(['A', 'B', 'C1'], $queries);
        $chained = array_map(static fn (Candidate $candidate): string|int|null => is_string($candidate->result) || is_int($candidate->result) || $candidate->result === null ? $candidate->result : throw new LogicException('Expected a scalar candidate.'), iterator_to_array($deriver->derive('wpdb::chain#parameter(query)'), false));
        sort($chained);
        self::assertSame(['CHAINED', 'FIRST', 'SECOND'], $chained);
    }

    public function testUnavailableCalleesLeaveGlobalsTheyWereNotGivenIntact(): void
    {
        file_put_contents(vfsStream::url('project') . '/wpdb.php', '<?php class wpdb{public function get_var($query){}} $wpdb=new wpdb(); $config=["table"=>"posts"];');
        file_put_contents(vfsStream::url('project') . '/app.php', '<?php function f(){global $wpdb,$config;undefined_fn();$wpdb->get_var("SELECT ".$config["table"]);}');
        $deriver = Deriver::prepare(['/project/wpdb.php', '/project/app.php']);
        self::assertSame(['SELECT posts'], array_map(static fn (Candidate $candidate): string|int|null => is_string($candidate->result) || is_int($candidate->result) || $candidate->result === null ? $candidate->result : throw new LogicException('Expected a scalar candidate.'), iterator_to_array($deriver->derive('wpdb::get_var#parameter(query)'), false)));
    }

    public function testCalleesPropagatePropertyWritesToObjectsTheyReceiveAsArguments(): void
    {
        file_put_contents(vfsStream::url('project') . '/app.php', '<?php function sink($s){}
            class Row{public $t="a";}
            function change($row){$row->t="b";}
            function direct(){$row=new Row();change($row);sink($row->t);}
            class Builder{private array $where=[];public function where($c){if($c instanceof \Closure){$c($this);return $this;}$this->where[]=$c;return $this;}public function sql(){return "WHERE ".implode(" AND ",$this->where);}}
            function closure(){$b=(new Builder())->where(fn($q)=>$q->where("x = 1"));sink($b->sql());}');
        $pruned = Deriver::prepare(['/project/app.php'], new DeriverConfig(evaluators: BranchEvaluators::truth()));
        $values = array_map(static fn (Candidate $candidate): string|int|null => is_string($candidate->result) || is_int($candidate->result) || $candidate->result === null ? $candidate->result : throw new LogicException('Expected a scalar candidate.'), iterator_to_array($pruned->derive('sink#parameter(s)'), false));
        sort($values);
        self::assertSame(['WHERE x = 1', 'b'], $values);
    }

    public function testDeclaredTypesDecideStrictComparisonsAndInstanceof(): void
    {
        file_put_contents(vfsStream::url('project') . '/app.php', '<?php function sink($s){}
            function nullCheck(string $s){sink($s===null?"null":"string");}
            function implicitNullable(string $s=null){sink($s===null?"maybe null":"maybe string");}
            function closure(){$f=fn()=>1;sink($f instanceof \Closure?"closure":"other");}
            function scalar(int $n){sink($n instanceof \Closure?"closure":"int");}');
        $pruned = Deriver::prepare(['/project/app.php'], new DeriverConfig(evaluators: BranchEvaluators::truth()));
        $values = array_map(static fn (Candidate $candidate): string|int|null => is_string($candidate->result) || is_int($candidate->result) || $candidate->result === null ? $candidate->result : throw new LogicException('Expected a scalar candidate.'), iterator_to_array($pruned->derive('sink#parameter(s)'), false));
        sort($values);
        self::assertSame(['closure', 'int', 'maybe null', 'maybe string', 'string'], $values);
    }

    public function testUndefinedLocalsReadAsNullInsideFunctions(): void
    {
        file_put_contents(vfsStream::url('project') . '/app.php', '<?php function sink($s){} function undefined(){sink($nothing);} function check(){sink(isset($nothing)?"set":"unset");} function dynamic($name){$$name=1;sink($nothing);}');
        $pruned = Deriver::prepare(['/project/app.php'], new DeriverConfig(evaluators: BranchEvaluators::truth()));
        $candidates = iterator_to_array($pruned->derive('sink#parameter(s)'), false);
        self::assertCount(1, array_filter($candidates, static fn (Candidate $candidate): bool => $candidate->result instanceof Term));
        $values = array_map(static fn (Candidate $candidate): string|int|null => is_string($candidate->result) || is_int($candidate->result) || $candidate->result === null ? $candidate->result : throw new LogicException('Expected a scalar candidate.'), array_values(array_filter($candidates, static fn (Candidate $candidate): bool => !$candidate->result instanceof Term)));
        sort($values);
        self::assertSame([null, 'unset'], $values);
    }
}
