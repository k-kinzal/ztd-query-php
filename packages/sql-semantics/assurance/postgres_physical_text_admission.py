"""Load the reviewed-input proposal for resolved PostgreSQL text roles."""

import json

from run import TOOL, digest, require

ADMISSION = "7a69055459387f62cd5eed479166a0555ae019ee0021a562fda10ccddc4e38db"
INVALID = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\InvalidConstruction"


def negative(domain):
    path = TOOL / "postgres-physical-text-admission-v2.json"
    require(digest(path) == ADMISSION, "postgres-physical-text-admission-mismatch")
    case = json.loads(path.read_text())["changes"][domain]["negative"]
    return case["type"], {"exception": INVALID, "message": case["message"]}
