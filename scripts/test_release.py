import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

from release import prepare


class ReleasePreparationTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.package('core')
        self.package('openai-compatible', {'aisdk/core': '^0.8.0'})
        self.package('provider', {'aisdk/core': '^0.8.0', 'aisdk/openai-compatible': '^0.8.0'})
        self.package('laravel', {'aisdk/core': '^0.8.0'})
        self.patch = patch('release.subprocess.check_output', return_value='a' * 40)
        self.patch.start()
        self.addCleanup(self.patch.stop)

    def package(self, name, requires=None):
        folder = self.root / name
        folder.mkdir()
        (folder / '.git').mkdir()
        (folder / 'composer.json').write_text(json.dumps({'name': 'aisdk/' + name, 'require': requires or {}}))

    def test_order_and_dry_run(self):
        before = (self.root / 'provider/composer.json').read_text()
        plan = prepare(self.root, '0.10.0')
        self.assertEqual([p['directory'] for p in plan], ['core', 'openai-compatible', 'provider'])
        self.assertEqual((self.root / 'provider/composer.json').read_text(), before)

    def test_write_is_idempotent_and_excludes_frameworks(self):
        before = (self.root / 'laravel/composer.json').read_text()
        prepare(self.root, '0.10.0', write=True)
        self.assertTrue(all(not p['changes'] for p in prepare(self.root, '0.10.0')))
        self.assertEqual((self.root / 'laravel/composer.json').read_text(), before)

    def test_rejects_prereleases_and_missing_dependencies_before_writes(self):
        for version in ('0.10.0-beta.1', 'v0.10.0', '0.10.1', '00.10.0'):
            with self.assertRaises(ValueError):
                prepare(self.root, version)
        self.package('missing', {'aisdk/nonexistent': '^0.8.0'})
        before = (self.root / 'openai-compatible/composer.json').read_text()
        with self.assertRaises(ValueError):
            prepare(self.root, '0.10.0', write=True)
        self.assertEqual((self.root / 'openai-compatible/composer.json').read_text(), before)

    def test_rejects_cycles(self):
        path = self.root / 'core/composer.json'
        path.write_text(json.dumps({'name': 'aisdk/core', 'require': {'aisdk/provider': '^0.8.0'}}))
        with self.assertRaises(ValueError):
            prepare(self.root, '0.10.0')


if __name__ == '__main__':
    unittest.main()
