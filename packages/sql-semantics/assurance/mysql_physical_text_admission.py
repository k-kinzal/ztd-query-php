"""Load the fixed negative declaration and its typed MySQL boundary."""

import json

from run import TOOL, digest, require

ADMISSION = "1a03057e1f901d6e680ca329471a99fd7b8b169c39d19fd678cd145891a3b3e2"


def negative():
    path = TOOL / "mysql-physical-text-admission-v1.json"
    require(digest(path) == ADMISSION, "mysql-physical-text-admission-mismatch")
    entry = json.loads(path.read_text())["negative"]
    return entry["declaredType"], {key: entry[key] for key in ("exception", "message")}
