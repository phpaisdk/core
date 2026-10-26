#!/usr/bin/env python3
"""Prepare synchronized stable feature releases. Never commits, pushes, or tags."""
import argparse
import json
from pathlib import Path
import re
import subprocess

EXCLUDED = {'laravel', 'symfony', 'local-aisdk'}


def packages(workspace):
    found = {}
    for path in sorted(workspace.glob('*/composer.json')):
        if path.parent.name in EXCLUDED or not (path.parent / '.git').exists():
            continue
        data = json.loads(path.read_text())
        name = data.get('name', '')
        if name.startswith('aisdk/'):
            if name in found:
                raise ValueError(f'Duplicate package: {name}')
            found[name] = (path, data)
    if 'aisdk/core' not in found or 'aisdk/openai-compatible' not in found:
        raise ValueError('Expected the SDK sibling-package workspace')
    return found


def ordered(found):
    pending = set(found)
    order = []
    while pending:
        ready = sorted(name for name in pending if not set(found[name][1].get('require', {})) & pending)
        if not ready:
            raise ValueError('Cyclic package dependencies')
        order.extend(ready)
        pending.difference_update(ready)
    return order


def prepare(workspace, version, write=False):
    if not re.fullmatch(r'(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.0', version):
        raise ValueError('Use a stable feature version such as 0.10.0 or 1.0.0')
    found = packages(workspace)
    plan = []
    for name in ordered(found):
        path, data = found[name]
        changes = {}
        for section in ('require', 'require-dev'):
            for dependency, constraint in data.get(section, {}).items():
                if dependency.startswith('aisdk/'):
                    if dependency not in found:
                        raise ValueError(f'{name} has missing SDK dependency {dependency}')
                    if constraint != '^' + version:
                        changes[f'{section}.{dependency}'] = {'from': constraint, 'to': '^' + version}
                        data[section][dependency] = '^' + version
        head = subprocess.check_output(['git', '-C', str(path.parent), 'rev-parse', 'HEAD'], text=True).strip()
        plan.append({'package': name, 'directory': path.parent.name, 'head': head, 'tag': 'v' + version, 'changes': changes})
    # Validate the complete plan before writing any manifest.
    if write:
        for item in plan:
            if item['changes']:
                path, data = found[item['package']]
                path.write_text(json.dumps(data, indent=4, ensure_ascii=False) + '\n')
    return plan


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('version')
    parser.add_argument('--workspace', type=Path, default=Path(__file__).resolve().parents[2])
    parser.add_argument('--write', action='store_true', help='Update only inter-package Composer constraints')
    args = parser.parse_args()
    try:
        print(json.dumps(prepare(args.workspace, args.version, args.write), indent=2))
    except (ValueError, subprocess.CalledProcessError) as error:
        parser.exit(1, f'{error}\n')


if __name__ == '__main__':
    main()
