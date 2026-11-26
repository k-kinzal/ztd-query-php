"""Replay PostgreSQL INSERT result positions and transactional effects."""

import run_server_postgres_insert_returning
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_insert_returning)
