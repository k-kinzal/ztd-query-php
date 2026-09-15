import json
from pathlib import Path
import shutil
import sys
import tempfile
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'bin'))
from package_policy import check, COMMON_FILES, ROOT


class PackagePolicyTest(unittest.TestCase):
    def fixture(self, directory):
        root = Path(directory)
        for name in ('ztd-query-core', 'ztd-query-mysql'):
            package = root / 'packages' / name
            package.mkdir(parents=True)
            for filename in COMMON_FILES:
                shutil.copyfile(ROOT / 'packages' / name / filename, package / filename)
            for name in ('src', 'tests', 'fuzz', 'bench'):
                (package / name).mkdir()
            manifest_path = package / 'composer.json'
            manifest = json.loads(manifest_path.read_text())
            manifest['repositories'] = [repository for repository in manifest['repositories']
                                        if repository['type'] != 'path']
            manifest_path.write_text(json.dumps(manifest))
        return root

    def test_consistent_packages_pass(self):
        with tempfile.TemporaryDirectory() as directory:
            self.assertEqual(check(self.fixture(directory)), [])

    def test_shared_constraints_and_toolkit_commits_cannot_drift(self):
        with tempfile.TemporaryDirectory() as directory:
            root = self.fixture(directory)
            package = root / 'packages/ztd-query-mysql'
            path = package / 'composer.json'
            manifest = json.loads(path.read_text())
            manifest['require-dev']['phpstan/phpstan'] = '^1.0'
            path.write_text(json.dumps(manifest))
            path = package / 'composer.lock'
            lock = json.loads(path.read_text())
            for dependency in lock['packages-dev']:
                if dependency['name'] == 'k-kinzal/php-ai-toolkit':
                    dependency['source']['reference'] = 'different-commit'
            path.write_text(json.dumps(lock))
            errors = check(root)
            self.assertTrue(any('phpstan/phpstan constraint' in error for error in errors))
            self.assertTrue(any('locked k-kinzal/php-ai-toolkit' in error for error in errors))

    def test_old_root_layout_and_reversed_dependencies_fail(self):
        with tempfile.TemporaryDirectory() as directory:
            root = self.fixture(directory)
            package = root / 'packages/ztd-query-core'
            (package / 'Integration').mkdir()
            path = package / 'composer.json'
            manifest = json.loads(path.read_text())
            manifest['require']['k-kinzal/ztd-query-mysql'] = 'dev-main'
            path.write_text(json.dumps(manifest))
            errors = check(root)
            self.assertTrue(any('unexpected root directory Integration' in error for error in errors))
            self.assertTrue(any('runtime dependencies cross' in error for error in errors))


if __name__ == '__main__':
    unittest.main()
