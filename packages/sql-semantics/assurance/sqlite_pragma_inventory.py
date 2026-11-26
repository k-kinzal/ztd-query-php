"""Extract SQLite's required PRAGMA namespace independently of the candidate implementation."""

import argparse
import hashlib
import json
import re
from pathlib import Path

from sqlite_engine import Database

SOURCE_DIGEST = "b947b84b8c45d1513ecf2df8f46bf099d9f6d7a0acac0928061ddbb4ea6fe54b"
ENGINE_SOURCE = "2024-12-07 20:39:59 2aabe05e2e8cae4847a802ee2daddc1d7413d8fc560254d93ee3e72c14685b6c"
ENTRY = re.compile(
    r'/\* zName:\s*\*/ "([^"]+)",\s*'
    r'/\* ePragTyp:\s*\*/ PragTyp_(\w+),\s*'
    r'/\* ePragFlg:\s*\*/ ([^\n]*?),\s*'
    r'/\* ColNames:\s*\*/ (\d+), (\d+),\s*'
    r'/\* iArg:\s*\*/ ([^\n]*?)\s*\}',
)
HEADING = re.compile(r'/\*\s*(\d+)\s*\*/ "([^"]+)",?')


def source_inventory(path):
    data = path.read_bytes()
    if hashlib.sha256(data).hexdigest() != SOURCE_DIGEST:
        raise ValueError("SQLite amalgamation differs from the fixed release source")
    text = data.decode()
    names = text.split("static const PragmaName aPragmaName[] = {", 1)[1].split("\n};", 1)[0]
    column_source = text.split("static const char *const pragCName[] = {", 1)[1].split("\n};", 1)[0]
    headings = {int(offset): name for offset, name in HEADING.findall(column_source)}
    matches = ENTRY.findall(names)
    if len(matches) != names.count("/* zName:"):
        raise ValueError("One or more native PRAGMA declarations could not be read")
    inventory = {}
    for name, family, flags, offset, width, argument in matches:
        if name in inventory:
            raise ValueError(f"Repeated native PRAGMA name: {name}")
        first, count = int(offset), int(width)
        columns = [headings[index] for index in range(first, first + count)] if count else [name]
        inventory[name] = {
            "family": family, "flags": re.findall(r"PragFlg_(\w+)", flags),
            "declaredColumns": columns, "nativeArgument": argument.strip(),
        }
    return inventory


def active_inventory(library):
    db = Database(library)
    try:
        if db.version != "3.47.2" or db.source != ENGINE_SOURCE:
            raise ValueError("SQLite execution source differs from the fixed release")
        return {
            "version": db.version, "source": db.source, "librarySha256": db.digest,
            "compileOptions": [row[0][1] for row in db._observe("PRAGMA compile_options", {}, preserve_order=True)["rows"]],
            "activeNames": [row[0][1] for row in db._observe("PRAGMA pragma_list", {}, preserve_order=True)["rows"]],
        }
    finally:
        db.check(db.close(db.db))


def inventory(source, library):
    required = source_inventory(source)
    active = active_inventory(library)
    compiled_names = active["activeNames"]
    if len(set(compiled_names)) != len(compiled_names) or set(compiled_names) - required.keys():
        raise ValueError("The native build reports unknown or duplicate PRAGMA names")
    return {"sourceSha256": SOURCE_DIGEST, "required": required, "engine": active,
            "unavailableInBuild": sorted(required.keys() - set(compiled_names))}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--sqlite-source", required=True, type=Path)
    parser.add_argument("--sqlite-library", required=True, type=Path)
    parser.add_argument("--out", required=True, type=Path)
    args = parser.parse_args()
    output = inventory(args.sqlite_source, args.sqlite_library)
    with args.out.open("x") as target:
        json.dump(output, target, indent=2, sort_keys=True)
        target.write("\n")


if __name__ == "__main__":
    main()
