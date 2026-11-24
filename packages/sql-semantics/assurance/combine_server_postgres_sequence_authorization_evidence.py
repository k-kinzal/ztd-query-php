"""Recheck PostgreSQL sequence authorization evidence across pinned releases."""

from combine_server_join_evidence import main
import run_server_postgres_sequence_authorization


if __name__ == "__main__":
    main(run_server_postgres_sequence_authorization)
