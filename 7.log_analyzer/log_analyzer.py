import argparse
import re
import sys
import time
from collections import Counter, defaultdict



SQLI      = "SQL Injection"
TRAVERSAL = "Path Traversal"
XSS       = "XSS"
CMDI      = "Command Injection"



PATTERNS = [
    
    (SQLI, re.compile(r"union\s+(?:all\s+)?select", re.IGNORECASE)),
    (SQLI, re.compile(r"'\s*or\s+'?1'?\s*=\s*'?1", re.IGNORECASE)),
    (SQLI, re.compile(r";\s*drop\s+table", re.IGNORECASE)),
    (SQLI, re.compile(r"sleep\s*\(\s*\d+\s*\)", re.IGNORECASE)),

    
    (TRAVERSAL, re.compile(r"\.\./|\.\.\\", re.IGNORECASE)),
    (TRAVERSAL, re.compile(r"%2e%2e[/\\%]", re.IGNORECASE)),          
    (TRAVERSAL, re.compile(r"/etc/passwd|/proc/self/environ|win\.ini", re.IGNORECASE)),

    
    (XSS, re.compile(r"<script[\s>]|javascript:|onerror\s*=", re.IGNORECASE)),

    
    (CMDI, re.compile(r";\s*(?:cat|ls|whoami|id|uname|wget|curl|nc)\b", re.IGNORECASE)),
]


IP_PATTERN = re.compile(r"^(\d{1,3}(?:\.\d{1,3}){3})")




argument_parser = argparse.ArgumentParser(
    description="scan a web server log for common attack signatures"
)
argument_parser.add_argument("logfile", help="path to the log file")
argument_parser.add_argument(
    "-q", "--quiet", action="store_true",
    help="suppress per-hit output, print only the summary",
)
args = argument_parser.parse_args()

counts        = Counter()            
attackers     = defaultdict(set)     
attacker_hits = Counter()            
total_lines   = 0
total_hits    = 0
start_time    = time.time()

try:
    with open(args.logfile, "r", encoding="utf-8", errors="ignore") as handle:
        for lineno, line in enumerate(handle, start=1):
            total_lines += 1

            
            matched_types = set()
            for attack_type, pattern in PATTERNS:
                if pattern.search(line):
                    matched_types.add(attack_type)

            if not matched_types:
                continue

            total_hits += 1

            ip_match = IP_PATTERN.match(line)
            attacker_ip = ip_match.group(1) if ip_match else "unknown"

            attacker_hits[attacker_ip] += 1
            for attack_type in matched_types:
                counts[attack_type] += 1
                attackers[attack_type].add(attacker_ip)

            if not args.quiet:
                tags = ", ".join(sorted(matched_types))
                print(f"[!] line {lineno:>6}  {attacker_ip:<15}  {tags}")

except FileNotFoundError:
    sys.exit(f"no such file: {args.logfile}")
except PermissionError:
    sys.exit(f"can't read {args.logfile}")

took = time.time() - start_time


print()
print(f"scanned {total_lines} lines in {took:.2f}s")
print(f"suspicious requests: {total_hits}")

if total_hits == 0:
    sys.exit(0)

print("\nby attack type:")
for attack_type, hits in counts.most_common():
    unique_ips = len(attackers[attack_type])
    print(f"  {attack_type:<20} {hits:>5} hit(s) from {unique_ips} IP(s)")

print("\ntop attackers:")
for attacker_ip, hits in attacker_hits.most_common(5):
    print(f"  {attacker_ip:<15} {hits:>5} hit(s)")