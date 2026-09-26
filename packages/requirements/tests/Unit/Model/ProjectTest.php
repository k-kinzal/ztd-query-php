<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Model\Project;
use Requirements\Model\Source;
use Requirements\Test\RunnerConfig;

#[CoversClass(Project::class)]
#[UsesClass(Source::class)]
#[UsesClass(RunnerConfig::class)]
#[Small]
final class ProjectTest extends TestCase
{
    public function testHoldsEveryPart(): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $runner = new RunnerConfig('phpunit', ['php', 'vendor/bin/phpunit'], '/project');
        $project = new Project('/project', [], ['manual' => $source], ['unit' => $runner], ['service' => 'App\ServiceSource'], ['custom' => 'App\CustomRunner'], 80.0, 90.0, ['manual' => 70.0], ['/project/requirements.yaml', '/project/definition.yaml'], ['profile' => 'strict']);
        self::assertSame('/project', $project->directory);
        self::assertSame([], $project->items);
        self::assertSame(['manual' => $source], $project->sources);
        self::assertSame(['unit' => $runner], $project->runners);
        self::assertSame(['service' => 'App\ServiceSource'], $project->sourceExtensions);
        self::assertSame(['custom' => 'App\CustomRunner'], $project->runnerExtensions);
        self::assertSame(80.0, $project->minimum);
        self::assertSame(90.0, $project->diffMinimum);
        self::assertSame(['manual' => 70.0], $project->sourceThresholds);
        self::assertSame(['/project/requirements.yaml', '/project/definition.yaml'], $project->files);
        self::assertSame(['profile' => 'strict'], $project->markdown);
    }

    public function testMarkdownOptionsDefaultToEmpty(): void
    {
        $project = new Project('/project', [], [], [], [], [], 0.0, 0.0, [], ['/project/requirements.yaml']);
        self::assertSame([], $project->markdown);
    }
}
