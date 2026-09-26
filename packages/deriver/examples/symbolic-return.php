<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Deriver\Analyzer;
use Deriver\Api\Project\ProjectInput;
use Deriver\Api\Project\SourceFile;
use Deriver\Api\Query\ReturnQuery;

$session = (new Analyzer())->open(new ProjectInput([
    new SourceFile('application.php', '<?php function keyFor(int $id): string {return "user:" . $id;}'),
]));

// The result retains the parameter and its concatenation; the application is never executed.
echo $session->derive(new ReturnQuery('keyFor'))->toJson() . PHP_EOL;
