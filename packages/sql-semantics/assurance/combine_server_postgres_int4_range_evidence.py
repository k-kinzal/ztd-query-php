"""Replay int4 range evidence on the pinned PostgreSQL releases."""

import run_server_postgres_int4_range
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_int4_range)
