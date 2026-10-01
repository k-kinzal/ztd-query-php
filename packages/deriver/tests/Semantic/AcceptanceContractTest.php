<?php

declare(strict_types=1);

namespace Tests\Semantic;

use Deriver\Project\Configuration;
use Deriver\Query\ReturnQuery;
use Deriver\Query\StateQuery;
use Deriver\Value\Projection;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;

/**
 * Checks symbolic expressions, correlated projections, iteration, and open dispatch through the public API.
 */
#[CoversNothing]
#[Small]
final class AcceptanceContractTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSymbolicConcatenationIsAClosedExpression(): void
    {
        $result = Analysis::returns('<?php function target(int $id):string{return "user:".$id;}');
        $value = $result->normalOutcomes[0]->values['return'];
        self::assertSame('concat', $value->kind);
        self::assertSame('user:', $value->operands[0]->native());
        self::assertSame('cast', $value->operands[1]->kind);
        self::assertSame('parameter', $value->operands[1]->operands[0]->kind);
        self::assertSame('closed', $result->assessment->closure);
        self::assertSame('exact-symbolic', $result->assessment->precision);
        self::assertSame([], $result->frontiers);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSeparateProjectionsRetainTheSameBranchIdentities(): void
    {
        $session = Analysis::session('<?php function observe($pair){}function target(bool $admin){$pair=$admin?["admins","admin_id"]:["users","user_id"];observe($pair);}');
        $point = $session->callsTo('observe')[0]->beforeInvocation();
        $tables = $session->derive(new StateQuery($point, 'pair', new Projection([0])));
        $columns = $session->derive(new StateQuery($point, 'pair', new Projection([1])));
        self::assertSame(['admins', 'users'], array_map(static fn ($outcome) => $outcome->values['state']->native(), $tables->normalOutcomes));
        self::assertSame(['admin_id', 'user_id'], array_map(static fn ($outcome) => $outcome->values['state']->native(), $columns->normalOutcomes));
        self::assertSame(array_column($tables->normalOutcomes, 'guard'), array_column($columns->normalOutcomes, 'guard'));
        self::assertNotSame($tables->normalOutcomes[0]->guard, $tables->normalOutcomes[1]->guard);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testKnownIterationUsesTheArrayReturnedByASourceHelper(): void
    {
        $result = Analysis::returns('<?php function items(){return ["a","b","c"];}function target(){$parts=[];foreach(items() as $item){$parts[]="[".$item."]";}return $parts;}');
        self::assertSame(['[a]', '[b]', '[c]'], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testOpenDispatchRetainsBothKnownImplementationsAndTheRemainder(): void
    {
        $source = '<?php interface I{function value():int;}class A implements I{function value():int{return 1;}}class B implements I{function value():int{return 2;}}function target(I $object){return $object->value();}';
        $closed = Analysis::session($source, new Configuration(closedWorld: true))->derive(new ReturnQuery('target'));
        $open = Analysis::returns($source);
        self::assertEqualsCanonicalizing([1, 2], array_map(static fn ($outcome) => $outcome->values['return']->native(), $closed->normalOutcomes));
        self::assertSame([], $closed->frontiers);
        self::assertContains(1, array_map(static fn ($outcome) => $outcome->values['return']->literal, $open->normalOutcomes));
        self::assertContains(2, array_map(static fn ($outcome) => $outcome->values['return']->literal, $open->normalOutcomes));
        self::assertContains('OPEN_DISPATCH', array_column($open->frontiers, 'code'));
        self::assertSame('open', $open->assessment->closure);
    }
}
