"""Replay native PostgreSQL Boolean mutation evidence on both required releases."""

import run_server_postgres_boolean_returning
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_boolean_returning)
