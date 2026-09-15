from pathlib import Path
import sys
import tempfile
import unittest
import xml.etree.ElementTree as ET

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'bin'))
from docs import combine_coverage


class CoverageTest(unittest.TestCase):
    def test_reports_with_same_filenames_keep_package_paths_and_test_links(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            packages = []
            for name in ('core', 'mysql'):
                package = root / 'packages' / name
                report = package / 'build/coverage-xml'
                report.mkdir(parents=True)
                source = package / 'src'
                (report / 'index.xml').write_text(f'<coverage xmlns="https://schema.phpunit.de/coverage/1.0"><project source="{source}"/></coverage>')
                (report / 'Query.php.xml').write_text(
                    '<coverage xmlns="https://schema.phpunit.de/coverage/1.0"><file name="Query.php" path="Sql">'
                    '<line nr="12"><covered by="Tests\\QueryTest::testQuery"/></line>'
                    '<method name="query" start="10" coverage="100"/>'
                    '</file></coverage>'
                )
                packages.append(package)
            output = root / 'build/coverage-xml'
            combine_coverage(root, packages, output)
            for name in ('core', 'mysql'):
                file = ET.parse(output / name / 'Query.php.xml').find('{*}file')
                self.assertEqual(file.get('path'), f'packages/{name}/src/Sql')
                self.assertEqual(file.find('{*}line/{*}covered').get('by'), 'Tests\\QueryTest::testQuery')
                self.assertEqual(file.find('{*}method').get('coverage'), '100')
            combine_coverage(root, packages[:1], output)
            self.assertFalse((output / 'mysql').exists())

    def test_missing_reports_fail_the_build(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            with self.assertRaises(FileNotFoundError):
                combine_coverage(root, [root / 'packages/core'], root / 'build/coverage-xml')


if __name__ == '__main__':
    unittest.main()
