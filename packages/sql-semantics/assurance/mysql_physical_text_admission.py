"""Load the fixed negative declaration and its typed MySQL boundary."""

import json

from run import TOOL, digest, require

ADMISSION = "2b3e592e2799f9f5a810d927c81a554eef8de52ebe370035f17947c7e6c22c25"


def negative():
    path = TOOL / "mysql-physical-text-admission-v2.json"
    require(digest(path) == ADMISSION, "mysql-physical-text-admission-mismatch")
    entry = json.loads(path.read_text())["negative"]
    return entry["declaredType"], {key: entry[key] for key in ("exception", "message")}
