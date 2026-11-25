"""Replay native MySQL foreign-key exchange and index replacement evidence."""

from combine_server_join_evidence import main
import run_server_mysql_foreign_key_exchange

if __name__ == "__main__":
    main(run_server_mysql_foreign_key_exchange)
