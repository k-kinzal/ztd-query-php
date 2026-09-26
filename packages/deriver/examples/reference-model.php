<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Deriver\Analyzer;
use Deriver\Api\Project\Configuration;
use Deriver\Api\Project\ProjectInput;
use Deriver\Api\Project\SourceFile;
use Deriver\Api\Query\ReturnQuery;
use Deriver\Model\Binding\LocationRef;
use Deriver\Model\CallDescription;
use Deriver\Model\CallModel;
use Deriver\Model\ModelDecision;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\CallArgument;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Signature\Parameter;
use Deriver\Model\Signature\Signature;

$model = new class () implements CallModel {
    public function descriptor(): ModelDescriptor
    {
        return new ModelDescriptor('example.apply', '1', 'apply', new Signature([
            new Parameter('value', byReference: true),
            new Parameter('callback', 'callable'),
        ]));
    }

    public function describe(CallDescription $call): ModelDecision
    {
        return ModelDecision::handled(new SemanticPlan([
            Action::invoke('@result', Expression::parameter('callback'), [
                new CallArgument(LocationRef::parameter('value')),
            ]),
            Action::returns(Expression::parameter('@result')),
        ], writes: ['parameter:value']));
    }
};

$session = (new Analyzer())->open(new ProjectInput([
    new SourceFile('application.php', <<<'SOURCE'
<?php
function demo(): array
{
    $value = 2;
    $result = apply($value, function (&$item) {return ++$item;});
    return [$result, $value];
}
SOURCE),
]), new Configuration(models: [$model]));

$result = $session->derive(new ReturnQuery('demo'));
echo json_encode($result->normalOutcomes[0]->values['return']->native(), JSON_THROW_ON_ERROR) . PHP_EOL;
// [3,3]
