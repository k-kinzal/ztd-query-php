<?php

declare(strict_types=1);

namespace Deriver\Analysis\Candidates;

/**
 * Former name of the replay check, kept for one release so existing callers keep working.
 * @deprecated Use {@see \Deriver\Replay} instead; this alias is removed in the next minor release.
 * @example The former name still revalidates a captured candidate set
 *     $input = new \Deriver\Project\ProjectInput([new \Deriver\Project\SourceFile('a.php', '<?php function f(){return 42;}')]);
 *     $query = new \Deriver\Query\ReturnQuery('f');
 *     $json = (new \Deriver\Analyzer())->open($input)->derive($query)->toJson();
 *     (new \Deriver\Analysis\Candidates\Replay())->verify($json, $input, new \Deriver\Project\Configuration(), $query);
 *     strlen($json) > 2 // => true
 * @visibility public
 */
final class Replay extends \Deriver\Replay
{
}
