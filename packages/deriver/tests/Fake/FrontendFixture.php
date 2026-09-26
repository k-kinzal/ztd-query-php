<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\Api\Project\ProjectInput;
use Deriver\Api\Project\SourceFile;
use Deriver\Api\Project\TargetProfile;
use Deriver\Internal\Frontend\Php\Lowering;
use Deriver\Internal\Frontend\Php\ProjectIndex;

/**
 * Supplies captured syntax and independent graph builders for compiler tests.
 * @visibility root
 */
final class FrontendFixture
{
    /**
     * @param string $source Captured PHP source
     * @return ProjectIndex Immutable source index with lazy graphs
     */
    public static function index(string $source = '<?php function target(){return 1;}'): ProjectIndex
    {
        return new ProjectIndex('test', new ProjectInput([new SourceFile('fixture.php', $source)]), new TargetProfile());
    }

    /**
     * @return Lowering New compiler context
     */
    public static function lowering(): Lowering
    {
        $index = self::index();
        return new Lowering($index->builder('fixture.php'), $index, 'target');
    }
}
