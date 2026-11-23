"""Load the reviewed-input proposal for resolved PostgreSQL text roles."""

import json

from run import TOOL, digest, require

ADMISSION = "52f64c9076735c48f875e8aa7c9b0631a98a5fbd931c00d74545cdf09bf758c5"
INVALID = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\InvalidConstruction"


def negative(domain):
    path = TOOL / "postgres-physical-text-admission-v4.json"
    require(digest(path) == ADMISSION, "postgres-physical-text-admission-mismatch")
    case = json.loads(path.read_text())["changes"][domain]["negative"]
    return case["type"], {"exception": INVALID, "message": case["message"]}
