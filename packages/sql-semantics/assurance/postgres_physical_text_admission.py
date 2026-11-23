"""Load the reviewed-input proposal for resolved PostgreSQL text roles."""

import json

from run import TOOL, digest, require

ADMISSION = "347cb6995fb309333d63330164d1da64b99b568cd18b7b7f16b7bcfdc0ac6a6c"
INVALID = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\InvalidConstruction"


def negative(domain):
    path = TOOL / "postgres-physical-text-admission-v3.json"
    require(digest(path) == ADMISSION, "postgres-physical-text-admission-mismatch")
    case = json.loads(path.read_text())["changes"][domain]["negative"]
    return case["type"], {"exception": INVALID, "message": case["message"]}
