#!/usr/bin/env python3
from pathlib import Path
import secrets

root = Path(__file__).resolve().parents[1]
env_path = root / ".env"
secret = secrets.token_hex(32)

lines = env_path.read_text().splitlines() if env_path.exists() else []
out = []
found = False
for line in lines:
    if line.startswith("APP_SECRET="):
        out.append(f"APP_SECRET={secret}")
        found = True
    else:
        out.append(line)
if not found:
    out.append(f"APP_SECRET={secret}")

env_path.write_text("\n".join(out) + "\n")
print(f"ok secret_len={len(secret)}")
