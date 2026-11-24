"""Replay index removal ledgers against the independent binding and catalog transition model."""

from combine_server_join_evidence import main
import run_server_postgres_drop_index


if __name__ == "__main__":
    main(run_server_postgres_drop_index)
