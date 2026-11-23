"""Replay both releases of PostgreSQL text scalar presence observations."""

import run_server_postgres_text_null_tests
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_text_null_tests)
