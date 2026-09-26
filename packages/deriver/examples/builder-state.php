<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Deriver\Analyzer;
use Deriver\Api\Project\Configuration;
use Deriver\Api\Project\ProjectInput;
use Deriver\Api\Project\SourceFile;
use Deriver\Api\Query\StateQuery;
use Deriver\Model\CallDescription;
use Deriver\Model\CallModel;
use Deriver\Model\ModelDecision;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Signature\Parameter;
use Deriver\Model\Signature\Signature;
use Deriver\Model\State\StateSlot;
use Deriver\Value\Projection;
use Deriver\Value\Term;

$model = new class () implements CallModel {
    public function descriptor(): ModelDescriptor
    {
        return new ModelDescriptor('example.builder.from', '1', 'Builder::from', new Signature([
            new Parameter('table', 'string'),
        ]));
    }

    public function describe(CallDescription $call): ModelDecision
    {
        return ModelDecision::handled(new SemanticPlan([
            Action::write('example.builder.table', Expression::parameter('table')),
            Action::returns(Expression::receiver()),
        ], writes: ['example.builder.table']));
    }
};

$session = (new Analyzer())->open(new ProjectInput([
    new SourceFile('application.php', '<?php class Builder {} function demo() {$a=new Builder;$a->from("users");$alias=$a;$alias->from("admins");observe($a);}'),
]), new Configuration(
    models: [$model],
    stateSlots: [new StateSlot('example.builder.table', 'string', Term::constant(''))],
));

$point = $session->callsTo('observe')[0]->beforeInvocation();
$result = $session->derive(new StateQuery($point, 'a', Projection::stateSlot('example.builder.table')));
echo $result->normalOutcomes[0]->values['state']->native() . PHP_EOL;
// admins
