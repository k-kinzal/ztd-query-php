"""Build one documentation site for every package, with combined coverage."""
import argparse
from pathlib import Path
import shutil
import subprocess
import xml.etree.ElementTree as ET

from package_policy import ROOT, packages


def combine_coverage(root, package_paths, output):
    if output.exists():
        shutil.rmtree(output)
    output.mkdir(parents=True)
    for package in package_paths:
        report = package / 'build/coverage-xml'
        project = next((element for element in ET.parse(report / 'index.xml').iter()
                        if element.tag.rsplit('}', 1)[-1] == 'project'), None)
        if project is None or not project.get('source'):
            raise ValueError(f'Missing coverage source in {report}/index.xml')
        prefix = Path(project.get('source')).resolve().relative_to(root.resolve())
        for source in sorted(report.rglob('*.xml')):
            if source.name == 'index.xml':
                continue
            document = ET.parse(source)
            if document.getroot().tag.startswith('{'):
                ET.register_namespace('', document.getroot().tag[1:].split('}', 1)[0])
            for file in document.iter():
                if file.tag.rsplit('}', 1)[-1] == 'file':
                    file.set('path', (prefix / file.get('path', '').lstrip('/')).as_posix())
            target = output / package.name / source.relative_to(report)
            target.parent.mkdir(parents=True, exist_ok=True)
            document.write(target, encoding='utf-8', xml_declaration=True)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--diff', default='')
    parser.add_argument('--base-url', default='')
    parser.add_argument('--jobs', default='2')
    args = parser.parse_args()
    combine_coverage(ROOT, packages(), ROOT / 'build/coverage-xml')
    command = [
        str(ROOT / 'packages/ztd-query-core/vendor/bin/docgen'),
        '--packages=packages/*', '--exclude=packages/*/src/Compatibility/ClassAliases.php',
        '--deptrac=' + str(ROOT / 'deptrac.yaml'), '--coverage=build/coverage-xml',
        '--output=build/docs', '--cache-dir=build/docgen-cache',
        '--title=ZTD Query PHP', '--repository=https://github.com/k-kinzal/ztd-query-php',
        '--jobs=' + args.jobs, '--memory-limit=2G',
    ]
    if args.diff:
        command.append('--diff=' + args.diff)
    if args.base_url:
        command.append('--base-url=' + args.base_url)
    subprocess.run(command, cwd=ROOT, check=True)
    for package in packages():
        if not (ROOT / 'build/docs/k-kinzal' / package.name / 'index.html').is_file():
            raise RuntimeError(f'DocGen did not generate {package.name}')


if __name__ == '__main__':
    main()
