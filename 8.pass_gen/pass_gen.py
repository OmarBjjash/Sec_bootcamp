
import argparse
import hashlib
import secrets
import string
import sys
import time


LOWERCASE = string.ascii_lowercase
UPPERCASE = string.ascii_uppercase
DIGITS    = string.digits
SYMBOLS   = string.punctuation

ALL_CHARS = LOWERCASE + UPPERCASE + DIGITS + SYMBOLS

def generate_password(length):
    
    if length < 4:
        raise ValueError("length must be at least 4 to fit one char per class")

    picks = [
        secrets.choice(LOWERCASE),
        secrets.choice(UPPERCASE),
        secrets.choice(DIGITS),
        secrets.choice(SYMBOLS),
    ]

    
    for _ in range(length - 4):
        picks.append(secrets.choice(ALL_CHARS))
    
    secrets.SystemRandom().shuffle(picks)

    return "".join(picks)

def sha256_hex(text):
   
    return hashlib.sha256(text.encode("utf-8")).hexdigest()


argument_parser = argparse.ArgumentParser(
    description="generate a strong password and show its SHA-256 hash"
)
argument_parser.add_argument(
    "-l", "--length", type=int, default=16,
    help="password length (default: 16, minimum: 4)",
)
argument_parser.add_argument(
    "-n", "--count", type=int, default=1,
    help="how many passwords to generate (default: 1)",
)
argument_parser.add_argument(
    "-H", "--hash", metavar="TEXT", 
    help="skip generation and just hash TEXT with SHA-256",
)
args = argument_parser.parse_args()

start_time = time.time()


if args.hash is not None:
    digest = sha256_hex(args.hash)
    print(f"input : {args.hash}")
    print(f"sha256: {digest}")
    sys.exit(0)


if args.length < 4:
    sys.exit("length must be at least 4")
if args.count < 1:
    sys.exit("count must be at least 1")

for i in range(args.count):
    password = generate_password(args.length)
    digest = sha256_hex(password)
    if args.count > 1:
        print(f"--- #{i + 1} ---")
    print(f"password: {password}")
    print(f"sha256  : {digest}")
    print()

took = time.time() - start_time
if args.count > 1:
    print(f"generated {args.count} passwords in {took:.4f}s")