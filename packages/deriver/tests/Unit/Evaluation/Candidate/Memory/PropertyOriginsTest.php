<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Memory;

use Deriver\Evaluation\Candidate\Choices;
use Deriver\Evaluation\Candidate\Memory\PropertyOrigins;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class PropertyOriginsTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testInitialReadsOnlyTheDeclarationDefault(): void
    {
        $engine = F::evaluator('class Box{public $name="ledger";function target(){$this->name="changed";}}');
        $property = $engine->context->index->property('Box', 'name');
        self::assertNotNull($property);
        self::assertSame('ledger', (new PropertyOrigins($engine))->initial($property, 64)->native());
        self::assertSame([], $engine->context->bodies);
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testValueAndWritesKeepCompoundStoredResults(): void
    {
        $engine = F::evaluator('class Box{public $name="ledger";function archive(){$this->name="ledger";$this->name.="_archive";}function target(){return $this->name;}}');
        $frame = F::frame($engine, 'Box::target');
        $property = $engine->context->index->property('Box', 'name');
        self::assertNotNull($property);
        $origins = new PropertyOrigins($engine);
        $result = $origins->value($frame, F::instruction($frame, 'field-address'), $property, 64);
        $values = array_map(static fn (array $choice) => $choice[0]->native(), (new Choices())->alternatives($result));
        self::assertSame(['ledger', 'ledger_archive'], $values);
        self::assertCount(2, $origins->writes($frame, F::frame($engine, 'Box::archive')->graph, $property, 64));
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testWritesUsesTheReadBeforeEachMutation(): void
    {
        $engine = F::evaluator('class Box{public $n=1;function target(){$this->n=4;$this->n++;}}');
        $frame = F::frame($engine, 'Box::target');
        $property = $engine->context->index->property('Box', 'n');
        self::assertNotNull($property);
        $writes = (new PropertyOrigins($engine))->writes($frame, $frame->graph, $property, 64);
        self::assertSame(4, $writes[0][0]->native());
        self::assertSame(5, $writes[1][0]->native());
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testSelectedWritesNeverEnterAReplacedBody(): void
    {
        $model = new \Tests\Fake\PlanModel(new \Deriver\Model\ModelDescriptor('change', '1', 'Box::change'), new \Deriver\Model\Plan\SemanticPlan([]));
        $engine = F::evaluator('class Box{public $n=1;function change(){$this->n=9;}}', new \Deriver\Project\Configuration(models: [$model]));
        $frame = F::frame($engine, 'Box::change');
        $property = $engine->context->index->property('Box', 'n');
        self::assertNotNull($property);
        [$inputs, $call] = (new \Deriver\Evaluation\Candidate\Invocation\Origin())->call($frame->graph);
        self::assertSame([], (new PropertyOrigins($engine))->selectedWrites($frame, $inputs, $call, 'Box::change', $property, 64));
        self::assertSame([], $engine->context->bodies);
    }

}
