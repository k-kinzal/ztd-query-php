"""Load the reviewed-input proposal for resolved PostgreSQL text roles."""

import json

from run import TOOL, digest, require

ADMISSION = "22250386fe0c13bb2f3fc2f406e7c2f19ec0a453899a48fafb1ad3ea481dbd44"
INVALID = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\InvalidConstruction"


def negative(domain):
    path = TOOL / "postgres-physical-text-admission-v5.json"
    require(digest(path) == ADMISSION, "postgres-physical-text-admission-mismatch")
    case = json.loads(path.read_text())["changes"][domain]["negative"]
    return case["type"], {"exception": INVALID, "message": case["message"]}
