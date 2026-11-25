"""Independently replay native MySQL physical and foreign-key exchange comparisons."""

from combine_server_join_evidence import main
import run_server_mysql_physical_exchange

if __name__ == "__main__":
    main(run_server_mysql_physical_exchange)
