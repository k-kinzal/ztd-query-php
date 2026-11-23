"""Replay native PostgreSQL Boolean field observations for each required release."""

import run_server_postgres_boolean_fields
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_boolean_fields)
