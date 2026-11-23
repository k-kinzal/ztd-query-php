"""Load the reviewed-input proposal for resolved PostgreSQL text roles."""

import json

from run import TOOL, digest, require

ADMISSION = "1904736eef3eadb830e9c4585977468eeae8d1edc14b458fdd98fc1f65a9f3cf"
INVALID = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\InvalidConstruction"


def negative(domain):
    path = TOOL / "postgres-physical-text-admission-v1.json"
    require(digest(path) == ADMISSION, "postgres-physical-text-admission-mismatch")
    case = json.loads(path.read_text())["changes"][domain]["negative"]
    return case["type"], {"exception": INVALID, "message": case["message"]}
