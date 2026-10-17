<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Selection;

use Deriver\Analysis\Selection\TargetResolution;
use Deriver\Exception\InvalidInputException;
use Deriver\Query\ParameterQuery;
use Deriver\Query\ReturnQuery;
use Deriver\Query\StateQuery;
use Deriver\Query\TupleQuery;
use Deriver\Query\ValueQuery;
use Deriver\Value\Projection;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class TargetResolutionTest extends TestCase
{
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testResolveBindsEverySupportedQueryFormToItsOwnerAndSelection(): void
    {
        $session = new \Deriver\Session\Session(new \Deriver\Project\ProjectInput([new \Deriver\Project\SourceFile('candidate.php', '<?php ' . 'function target($x){$y = $x; observe($y, 2);}')]), new \Deriver\Project\Configuration());
        $resolution = new TargetResolution($session->program, $session->snapshot()->id);
        $site = $session->callsTo('observe')[0];

        $return = $resolution->resolve(new ReturnQuery('target'));
        self::assertSame(['target', 'return', 'target'], [$return->owner, $return->kind, $return->selector]);

        $parameter = $resolution->resolve(new ParameterQuery('target', 'x'));
        self::assertSame(['target', 'parameter', 'x', 'x'], [$parameter->owner, $parameter->kind, $parameter->selector, $parameter->parameter]);

        $value = $resolution->resolve(new ValueQuery($site->argument(0), new Projection([0])));
        self::assertSame(['target', 'value', $site->argument(0)->register], [$value->owner, $value->kind, $value->register]);
        self::assertSame($site->argument(0)->source, $value->source);
        self::assertSame([0], $value->projection?->path);

        $tuple = $resolution->resolve(new TupleQuery($site->beforeInvocation(), ['left' => $site->argument(0), 'right' => $site->argument(1)]));
        self::assertSame('tuple', $tuple->kind);
        self::assertSame(['left' => $site->argument(0)->register, 'right' => $site->argument(1)->register], $tuple->registers);

        $state = $resolution->resolve(new StateQuery($site->beforeInvocation(), 'y'));
        self::assertSame(['target', 'state', 'y', 'y'], [$state->owner, $state->kind, $state->selector, $state->variable]);
        self::assertSame($site->beforeInvocation()->instruction, $state->point?->instruction);
    }

    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testResolveRejectsAReferenceFromAnotherSnapshot(): void
    {
        $session = new \Deriver\Session\Session(new \Deriver\Project\ProjectInput([new \Deriver\Project\SourceFile('candidate.php', '<?php ' . 'function target($x){observe($x);}')]), new \Deriver\Project\Configuration());
        $other = new \Deriver\Session\Session(new \Deriver\Project\ProjectInput([new \Deriver\Project\SourceFile('candidate.php', '<?php ' . 'function target($x){observe($x, 1);}')]), new \Deriver\Project\Configuration());
        $this->expectException(InvalidInputException::class);
        (new TargetResolution($session->program, $session->snapshot()->id))->resolve(new ValueQuery($other->callsTo('observe')[0]->argument(0)));
    }
}
