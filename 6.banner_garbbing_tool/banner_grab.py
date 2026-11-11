import argparse
import socket
import ssl
import sys
import time
from concurrent.futures import ThreadPoolExecutor, as_completed


HTTP_PORTS = {80, 8000, 8008, 8080, 8888}

HTTPS_PORTS = {443, 8443}

DEFAULT_PORTS = "21,22,25,80,110,143,443,3306,5432,6379,8080"


def grab_banner(target, port, timeout):
    my_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    my_socket.settimeout(timeout)
    try:
        my_socket.connect((target, port))
    except OSError:
        my_socket.close()
        return port, None

    try:
        if port in HTTPS_PORTS:
            context = ssl.create_default_context()
            context.check_hostname = False
            context.verify_mode = ssl.CERT_NONE
            try:
                my_socket = context.wrap_socket(my_socket, server_hostname=target)
            except (ssl.SSLError, OSError):
                return port, None


        my_socket.settimeout(min(timeout, 1.0))
        banner = b""
        try:
            banner = my_socket.recv(1024)
        except socket.timeout:
            pass

        if not banner and port in HTTP_PORTS:
            request = (
                f"HEAD / HTTP/1.1\r\n"
                f"Host: {target}\r\n"
                f"Connection: close\r\n\r\n"
            )
            my_socket.settimeout(timeout)
            try:
                my_socket.sendall(request.encode())
                banner = my_socket.recv(1024)
            except (socket.timeout, OSError):
                pass

        if not banner:
            return port, None

        text = banner.decode("utf-8", errors="ignore")
        first_line = text.splitlines()[0].strip() if text else ""
        return port, first_line
    finally:
        try:
            my_socket.close()
        except OSError:
            pass


def parse_ports(spec):
    ports = set()
    for part in spec.split(","):
        part = part.strip()
        if not part:
            continue
        if "-" in part:
            lo, hi = part.split("-", 1)
            ports.update(range(int(lo), int(hi) + 1))
        else:
            ports.add(int(part))
    return sorted(ports)




argument_parser = argparse.ArgumentParser(
    description="grab service banners from open TCP ports"
)
argument_parser.add_argument("target", help="IP or hostname")
argument_parser.add_argument(
    "-p", "--ports", default=DEFAULT_PORTS,
    help=f"ports to probe, e.g. 22,80,443 or 1-1024 (default: {DEFAULT_PORTS})",
)
argument_parser.add_argument(
    "-t", "--timeout", type=float, default=2.0,
    help="per-port timeout in seconds",
)
argument_parser.add_argument(
    "-w", "--workers", type=int, default=20,
    help="thread count",
)
args = argument_parser.parse_args()

try:
    ports = parse_ports(args.ports)
except ValueError:
    sys.exit("bad --ports value, expected something like 22,80 or 1-1024")

if not ports:
    sys.exit("no ports to scan")

try:
    ip = socket.gethostbyname(args.target)
except socket.gaierror:
    sys.exit(f"can't resolve {args.target}")

if ip != args.target:
    print(f"resolved {args.target} -> {ip}")

print(f"grabbing banners from {ip} ({len(ports)} port(s))\n")

start_time = time.time()
found = 0

with ThreadPoolExecutor(max_workers=args.workers) as pool:
    jobs = [pool.submit(grab_banner, ip, p, args.timeout) for p in ports]
    for job in as_completed(jobs):
        port, banner = job.result()
        if banner:
            found += 1
            print(f"{port:>5}  {banner}")
        else:
            print(f"{port:>5}  (no banner)")

took = time.time() - start_time
print(f"\ndone in {took:.2f}s, {found} banner(s) captured")