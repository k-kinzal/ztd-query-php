<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\Api\Project\Configuration;
use Deriver\Api\Project\ProjectInput;
use Deriver\Api\Project\SourceFile;
use Deriver\Api\Query\Budget;
use Deriver\Api\Query\ReturnQuery;
use Deriver\Internal\Frontend\Php\ProjectIndex;
use Deriver\Internal\Model\Registry;
use Deriver\Internal\Solver\Context;

/**
 * Supplies small captured source worlds for internal contract tests.
 * @visibility root
 */
final class SolverFixture
{
    /**
     * Creates independent query-local state without running application code.
     * @param string $source Fixture source bytes
     * @param Budget $budget Logical test budget
     * @param Configuration $configuration Captured test models and assumptions
     * @return Context Fresh solver context
     */
    public static function context(string $source = '<?php function target(){return 1;}', Budget $budget = new Budget(), Configuration $configuration = new Configuration()): Context
    {
        $program = new ProjectIndex('test', new ProjectInput([new SourceFile('fixture.php', $source)]), $configuration->target);
        return new Context($program, new ReturnQuery('target', budget: $budget), $configuration, new Registry($configuration));
    }
}
