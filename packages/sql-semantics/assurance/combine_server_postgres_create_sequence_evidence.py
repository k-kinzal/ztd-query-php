"""Reconcile native PostgreSQL sequence evidence and exact release receipts."""

from combine_server_join_evidence import main
import run_server_postgres_create_sequence

if __name__ == "__main__":
    main(run_server_postgres_create_sequence)
