<?php

declare(strict_types=1);

namespace Requirements\Tests\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Yaml\Yaml;

final class Workspace
{
    public readonly string $directory;

    public function __construct()
    {
        $this->directory = sys_get_temp_dir() . '/requirements-test-' . bin2hex(random_bytes(8));
        if (!mkdir($this->directory, 0700)) {
            throw new RuntimeException('Cannot create test workspace.');
        }
        copy(__DIR__ . '/../Fixtures/source.html', $this->directory . '/source.html');
        $this->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.yaml'], 'coverage' => ['minimum' => 0]]);
        $this->write('definition.yaml', ['version' => 1, 'source' => ['id' => 'manual', 'uri' => 'source.html', 'format' => 'html', 'selector' => 'main p'], 'items' => [self::item()]]);
    }

    /** @return array<string, mixed> */
    public static function item(): array
    {
        return ['id' => 'SPEC-001', 'statement' => 'When a name is read, the parser shall require a leading letter.', 'evidence' => [['selector' => '#a', 'quote' => 'Names shall start with a letter.']]];
    }

    /** @param array<string, mixed> $data */
    public function write(string $file, array $data): void
    {
        file_put_contents($this->directory . '/' . $file, Yaml::dump($data, 12, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE));
    }

    public function __destruct()
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file instanceof SplFileInfo) {
                if ($file->isDir()) {
                    rmdir($file->getPathname());
                } else {
                    unlink($file->getPathname());
                }
            }
        }
        rmdir($this->directory);
    }
}
