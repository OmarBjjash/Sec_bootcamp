import argparse
import socket
import sys
import time
from concurrent.futures import ThreadPoolExecutor, as_completed


def scan(target, port, timeout):
    my_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    my_socket.settimeout(timeout)
    try:
        return port, my_socket.connect_ex((target, port)) == 0
    except OSError:
        return port, False
    finally:
        my_socket.close()


def scan_range(target, start, end, workers, timeout):
    open_ports = []
    start_time = time.time()

    with ThreadPoolExecutor(max_workers=workers) as pool:
        jobs = [pool.submit(scan, target, p, timeout) for p in range(start, end + 1)]
        for job in as_completed(jobs):
            port, is_open = job.result()
            if is_open:
                open_ports.append(port)
                print(f"port {port} is open")

    took = time.time() - start_time
    print(f"\ndone in {took:.2f}s, {len(open_ports)} open port(s)")
    if open_ports:
        print("open:", sorted(open_ports))
    return open_ports



argument_parser = argparse.ArgumentParser(description="simple TCP port scanner")
argument_parser.add_argument("target", help="IP or hostname to scan")
argument_parser.add_argument("-p", "--ports", default="1-1024",
                             help="range like 1-1024 (default: 1-1024)")
argument_parser.add_argument("-t", "--timeout", type=float, default=1.0,
                             help="per-port timeout in seconds")
argument_parser.add_argument("-w", "--workers", type=int, default=50,
                             help="thread count")
args = argument_parser.parse_args()

try:
    low, high = args.ports.split("-")
    low, high = int(low), int(high)
except ValueError:
    sys.exit("bad --ports value, expected something like 1-1024")

if not (0 < low <= high <= 65535):
    sys.exit("port range must be between 1 and 65535")

try:
    ip = socket.gethostbyname(args.target)
except socket.gaierror:
    sys.exit(f"can't resolve {args.target}")

if ip != args.target:
    print(f"resolved {args.target} -> {ip}")

print(f"scanning {ip} ports {low}-{high} with {args.workers} threads\n")

try:
    scan_range(ip, low, high, args.workers, args.timeout)
except KeyboardInterrupt:
    print("\ninterrupted")
    sys.exit(1)