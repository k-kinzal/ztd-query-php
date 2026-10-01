<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Deriver\Analyzer;
use Deriver\Exception\InvalidInputException;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\Budget;
use Deriver\Query\ReturnQuery;
use JsonException;
use RuntimeException;

/**
 * Exercises parsing, lowering, bounded interpretation, and warm-result consistency.
 * @visibility root
 */
final class DeriveTarget
{
    /**
     * Treats fuzz input exclusively as source data, including malformed programs.
     * @param string $input Arbitrary function-body bytes
     * @throws JsonException If a result cannot be encoded
     * @throws RuntimeException If query caching changes a completed result
     */
    public function __invoke(string $input): void
    {
        $source = '<?php function target($input) {' . $input . '}';
        try {
            $session = (new Analyzer())->open(new ProjectInput([new SourceFile('fuzz.php', $source)]));
        } catch (InvalidInputException $failure) {
            if (str_starts_with($failure->getMessage(), 'SOURCE_LIMIT:')) {
                return;
            }
            throw $failure;
        }
        $query = new ReturnQuery('target', budget: new Budget(transfers: 1000, partitions: 4, iterations: 4, recursion: 4, nodes: 1024));
        $cold = $session->derive($query);
        $warm = $session->derive($query);
        if ($cold->toJson() !== $warm->toJson()) {
            throw new RuntimeException('Warm evaluation changed an immutable query result.');
        }
    }
}
