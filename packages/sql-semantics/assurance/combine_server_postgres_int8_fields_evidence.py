"""Replay exact PostgreSQL int8 field observations and narrowing errors."""

import run_server_postgres_int8_fields
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_int8_fields)
