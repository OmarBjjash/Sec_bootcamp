
import argparse
import hashlib
import json
import os
import sys
import time


CHUNK_SIZE = 65536


def file_sha256(path):
    
    digest = hashlib.sha256()
    with open(path, "rb") as handle:
        while True:
            chunk = handle.read(CHUNK_SIZE)
            if not chunk:
                break
            digest.update(chunk)
    return digest.hexdigest()


def load_baselines(state_path):
    try:
        with open(state_path, "r", encoding="utf-8") as handle:
            return json.load(handle)
    except (FileNotFoundError, json.JSONDecodeError):
        
        return {}

def save_baselines(state_path, baselines):
    
    
    tmp_path = state_path + ".tmp"
    with open(tmp_path, "w", encoding="utf-8") as handle:
        json.dump(baselines, handle, indent=2)
    os.replace(tmp_path, state_path)

argument_parser = argparse.ArgumentParser(
    description="monitor files for unauthorized changes via SHA-256"
)
argument_parser.add_argument(
    "files", nargs="+",
    help="one or more files to watch",
)
argument_parser.add_argument(
    "-i", "--interval", type=float, default=3.0,
    help="seconds between checks (default: 3)",
)
argument_parser.add_argument(
    "-s", "--state", default="fim_state.json",
    help="where to store baseline hashes (default: fim_state.json)",
)
argument_parser.add_argument(
    "--reset", action="store_true",
    help="discard existing baselines and re-snapshot the files",
)
args = argument_parser.parse_args()

if args.interval <= 0:
    sys.exit("interval must be a positive number")



targets = [os.path.abspath(p) for p in args.files]


existing = []
for path in targets:
    if os.path.isfile(path):
        existing.append(path)
    else:
        print(f"[!] skipping {path}: not a regular file")

if not existing:
    sys.exit("no files to monitor")


if args.reset:
    baselines = {}
else:
    baselines = load_baselines(args.state)

start_time = time.time()

print(f"watching {len(existing)} file(s), interval {args.interval}s")
print(f"state file: {args.state}\n")

changed = False
for path in existing:
    if path in baselines:
        print(f"[*] {path} — baseline loaded")
        continue

    try:
        baselines[path] = file_sha256(path)
    except (PermissionError, OSError) as err:
        print(f"[!] can't read {path}: {err}")
        continue

    print(f"[+] {path} — baseline created: {baselines[path][:16]}...")
    changed = True

if changed:
    save_baselines(args.state, baselines)

missing = set()

try:
    while True:
        time.sleep(args.interval)

        for path in existing:
            
            if not os.path.exists(path):
                if path not in missing:
                    missing.add(path)
                    stamp = time.strftime("%Y-%m-%d %H:%M:%S")
                    print(f"\n[CRITICAL] {stamp}  {path} was deleted")
                continue

            
            if path in missing:
                missing.discard(path)
                stamp = time.strftime("%Y-%m-%d %H:%M:%S")
                print(f"\n[CRITICAL] {stamp}  {path} reappeared — re-snapshotting")
                try:
                    baselines[path] = file_sha256(path)
                    save_baselines(args.state, baselines)
                except (PermissionError, OSError) as err:
                    print(f"[!] can't re-snapshot {path}: {err}")
                continue

            
            try:
                current = file_sha256(path)
            except (PermissionError, OSError) as err:
                print(f"[!] can't read {path}: {err}")
                continue

            if current != baselines.get(path):
                stamp = time.strftime("%Y-%m-%d %H:%M:%S")
                print(f"\n[CRITICAL] {stamp}  {path} was modified")
                print(f"           expected: {baselines.get(path)}")
                print(f"           actual  : {current}")
                baselines[path] = current
                save_baselines(args.state, baselines)

except KeyboardInterrupt:
    took = time.time() - start_time
    print(f"\nstopped after {took:.1f}s")
    sys.exit(0)