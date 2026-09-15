"""Check shared package conventions and resolved dependency versions."""
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
COMMON_FILES = (
    '.gitattributes', '.gitignore', '.php-cs-fixer.dist.php', 'LICENSE',
    'README.md', 'composer.json', 'composer.lock', 'deptrac.yaml',
    'infection.json5', 'loc.yaml', 'phpbench.json.dist', 'phpcs.xml.dist',
    'phpstan.neon', 'phpunit.xml.dist', 'tree.yaml',
)
IDENTICAL_FILES = ('.gitattributes', '.gitignore', 'phpbench.json.dist', 'phpcs.xml.dist', 'tree.yaml')
COMMON_SCRIPTS = (
    'test', 'test:unit', 'test:coverage', 'lint', 'format', 'format:check',
    'phpstan', 'compat', 'loc-guard', 'tree-guard', 'deptrac', 'deptrac:debug',
    'bench', 'bench:quick', 'bench:full', 'doctest', 'docgen:serve',
    'docgen:diff', 'docgen:fresh',
)


def packages(root=ROOT):
    return sorted(path.parent for path in (root / 'packages').glob('*/composer.json'))


def check(root=ROOT):
    errors = []
    constraints, locked, scripts, files = {}, {}, {}, {}
    configurations = {}
    for package in packages(root):
        manifest = json.loads((package / 'composer.json').read_text())
        name = package.name
        expected = set(COMMON_FILES) | {'src', 'tests', 'fuzz', 'bench'}
        if name == 'sql-faker':
            expected |= {'bin', 'docs', 'resources'}
        tracked = {p.name for p in package.iterdir() if p.name in expected}
        for missing in sorted(expected - tracked):
            errors.append(f'{name}: missing {missing}')
        for directory in package.iterdir():
            if directory.is_dir() and directory.name not in expected | {
                'vendor', 'build', '.git', '.idea', '.phpunit.cache', '.phpstan.cache',
            }:
                errors.append(f'{name}: unexpected root directory {directory.name}')
        for filename in IDENTICAL_FILES:
            content = (package / filename).read_text()
            if files.setdefault(filename, content) != content:
                errors.append(f'{name}: {filename} differs from the other packages')
        for command in COMMON_SCRIPTS:
            value = manifest['scripts'].get(command)
            if value is None or scripts.setdefault(command, value) != value:
                errors.append(f'{name}: inconsistent Composer script {command}')
        docgen = manifest['scripts'].get('docgen', '').replace(manifest['name'], '<package>')
        if not docgen or scripts.setdefault('docgen', docgen) != docgen:
            errors.append(f'{name}: inconsistent DocGen command')
        if configurations.setdefault('composer', manifest['config']) != manifest['config']:
            errors.append(f'{name}: inconsistent Composer configuration')
        expected_autoload = {'psr-4': {'Tests\\': 'tests/', 'Fuzz\\': 'fuzz/', 'Bench\\': 'bench/'}}
        if manifest['autoload-dev'] != expected_autoload:
            errors.append(f'{name}: inconsistent development autoloading')
        if manifest['config']['platform']['php'] != '8.1.0':
            errors.append(f'{name}: resolve dependencies against PHP 8.1.0')
        for section in ('require', 'require-dev'):
            for dependency, version in manifest.get(section, {}).items():
                if constraints.setdefault(dependency, version) != version:
                    errors.append(f'{name}: {dependency} constraint {version} differs from {constraints[dependency]}')
        lock = json.loads((package / 'composer.lock').read_text())
        for dependency in lock['packages'] + lock['packages-dev']:
            version = (dependency['version'], dependency.get('source', {}).get('reference'))
            key = dependency['name']
            if locked.setdefault(key, version) != version:
                errors.append(f'{name}: locked {key} {version} differs from {locked[key]}')
        for repository in manifest['repositories']:
            if repository['type'] == 'path':
                sibling = (package / repository['url']).resolve()
                key = json.loads((sibling / 'composer.json').read_text())['name']
                if repository.get('options', {}).get('versions') != {key: 'dev-main'}:
                    errors.append(f'{name}: {key} path version must be dev-main')
        internal = {key for key in manifest['require'] if key.startswith('k-kinzal/')}
        allowed = {
            'ztd-query-core': set(),
            'ztd-query-mysql': {'k-kinzal/ztd-query-core'},
            'ztd-query-postgres': {'k-kinzal/ztd-query-core'},
            'ztd-query-sqlite': {'k-kinzal/ztd-query-core'},
            'ztd-query-pdo-adapter': {'k-kinzal/ztd-query-core'},
            'ztd-query-mysqli-adapter': {'k-kinzal/ztd-query-core', 'k-kinzal/ztd-query-mysql'},
        }
        if name in allowed and internal != allowed[name]:
            errors.append(f'{name}: runtime dependencies cross the package layers')
    return errors


if __name__ == '__main__':
    failures = check()
    if failures:
        raise SystemExit('\n'.join(failures))
    print(f'Package conventions and dependency versions agree across {len(packages())} packages.')
