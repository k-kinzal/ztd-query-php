<?php

declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';
use SqlFaker\Generation\Plan\{GenerationPlan};

$g = $argv[1];
$provider = new SqlFaker\MySql\MySqlProvider(Faker\Factory::create(), $g);
$planner = $provider->planner();
$comp = new SqlFaker\Generation\Choice\BytePlanCompiler();
$plans = [
 'bare' => GenerationPlan::fromRule('expr')->requiringNonEmpty(),
 'budget' => GenerationPlan::fromRule('expr')->requiringNonEmpty()->withExpansionBudget(96),
 'named' => (new Fuzz\Target\Plans())->named(GenerationPlan::fromRule('expr')->requiringNonEmpty(), $g),
 'named84' => (new Fuzz\Target\Plans())->named(GenerationPlan::fromRule('expr')->requiringNonEmpty()),
];
foreach ($plans as $k => $c) {
    mt_srand(3);
    $in = '';
    for ($b = 0;$b < 100;$b++) {
        $in .= chr(mt_rand(0, 255));
    }
    try {
        echo $k, ': ', $provider->generate($comp->compile($in, $planner, $c)),"\n";
    } catch (Throwable $e) {
        echo "$k !! ", $e->getMessage(),"\n";
    }
}
