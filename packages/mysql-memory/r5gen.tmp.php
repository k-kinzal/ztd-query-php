<?php

declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';
$g = $argv[1];
$mode = $argv[2];
$provider = new SqlFaker\MySql\MySqlProvider(Faker\Factory::create(), $g);
$planner = $provider->planner();
$plans = new Fuzz\Target\Plans();
$c = $plans->plan($mode, $g);
$comp = new SqlFaker\Generation\Choice\BytePlanCompiler();
mt_srand(3);
for ($i = 0;$i < (int)($argv[3] ?? 10);$i++) {
    $in = '';
    for ($b = 0,$l = mt_rand(8, 200);$b < $l;$b++) {
        $in .= chr(mt_rand(0, 255));
    }
    try {
        echo $plans->statement($mode, $provider->generate($comp->compile($in, $planner, $c))),"\n";
    } catch (Throwable $e) {
        echo '!! ',get_class($e), $e->getMessage(),"\n";
    }
}
