<?php

declare(strict_types=1);

namespace Tests\Semantic;

use Deriver\Query\ReturnQuery;
use Deriver\Query\ValueQuery;
use Deriver\Result\Alternative;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;

/**
 * Goto and labels transfer control without disturbing unrelated state.
 */
#[CoversNothing]
#[Medium]
final class GotoTest extends TestCase
{
    /**
     * @param string $body Function body using goto or labels
     * @throws JsonException If captured fixture values cannot be encoded
     */
    #[DataProvider('jumps')]
    public function testUnrelatedTypedParametersKeepTheirType(string $body): void
    {
        $session = Analysis::session('<?php function target(PDO $pdo, $n){' . $body . '$pdo->query("SELECT 1");}');
        $call = $session->callsTo('query')[0];
        $receiver = $session->derive(new ValueQuery($call->receiver ?? $call->argument(0)));
        self::assertNotEmpty($receiver->normalOutcomes);
        foreach ($receiver->normalOutcomes as $outcome) {
            self::assertSame('parameter', $outcome->values['value']->kind);
            self::assertSame('PDO', $outcome->values['value']->attributes['type']);
        }
        $sql = $session->derive(new ValueQuery($call->argument(0)));
        self::assertSame(['SELECT 1'], array_values(array_unique(array_map(static fn (Alternative $outcome) => $outcome->values['value']->native(), $sql->normalOutcomes), SORT_REGULAR)));
        $operations = array_column([...$receiver->frontiers, ...$sql->frontiers], 'operation');
        self::assertNotContains('Stmt_Goto', $operations);
        self::assertNotContains('Stmt_Label', $operations);
    }

    /**
     * @return array<string, array{string}> Function bodies preceding the observed call
     */
    public static function jumps(): array
    {
        return [
            'label alone' => ['start:'],
            'backward loop' => ['$i=0;start:$i++;if($i<$n)goto start;'],
            'forward skip' => ['goto done;$pdo=null;done:'],
            'out of foreach' => ['foreach($n as $v){if($v)goto done;}done:'],
        ];
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testForwardGotoSkipsTheStatementsBetween(): void
    {
        $result = Analysis::returns('<?php function target($n){$x="a";if($n)goto done;$x="b";done:return $x;}');
        $actual = array_map(static fn (Alternative $outcome) => $outcome->values['return']->native(), $result->normalOutcomes);
        sort($actual);
        self::assertSame(['a', 'b'], $actual);
        self::assertSame([], $result->frontiers);
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testScriptLevelLabelsAreResolved(): void
    {
        $session = Analysis::session('<?php function sink($value){} $i=0; again: $i++; if($i<3) goto again; sink("v$i");');
        $result = $session->derive(new ValueQuery($session->callsTo('sink')[0]->argument(0)));
        self::assertSame(['v3'], array_map(static fn (Alternative $outcome) => $outcome->values['value']->native(), $result->normalOutcomes));
        self::assertSame([], $result->frontiers);
    }

    /**
     * @param string $source Jump PHP rejects or Deriver does not model
     * @throws JsonException If captured fixture values cannot be encoded
     */
    #[DataProvider('boundaries')]
    public function testUnmodeledJumpsSealThePathInsteadOfFallingThrough(string $source): void
    {
        $result = (Analysis::session($source))->derive(new ReturnQuery('target'));
        self::assertContains('UNSUPPORTED_LANGUAGE_FEATURE', array_column($result->frontiers, 'code'));
        self::assertContains('Stmt_Goto', array_column($result->frontiers, 'operation'));
        self::assertNotEmpty($result->normalOutcomes);
        foreach ($result->normalOutcomes as $outcome) {
            self::assertSame('opaque', $outcome->values['return']->kind);
        }
    }

    /**
     * @return array<string, array{string}> Fixtures whose goto cannot be followed
     */
    public static function boundaries(): array
    {
        return [
            'undefined label' => ['<?php function target(){$x="a";goto missing;$x="b";return $x;}'],
            'duplicate label' => ['<?php function target(){$x="a";goto twice;twice:$x="b";twice:return $x;}'],
            'into a try block' => ['<?php function target(){$x="a";goto inside;$x="b";try{inside:return $x;}finally{$x="c";}}'],
            'out of finally' => ['<?php function target(){$x="a";try{}finally{goto out;}$x="b";out:return $x;}'],
        ];
    }
}
