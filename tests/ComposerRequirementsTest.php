<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * The Composer manifest checked against the code it claims to describe.
 *
 * Both invariants here were review conventions that reviews then missed. `ext-ctype` went
 * undeclared while `IpAllowlist` called `ctype_digit()` unconditionally: `ctype` is a
 * bundled extension a build can still drop with `--disable-ctype`, so the dependency was
 * real and silent. And "no runtime dependencies beyond the extensions" is a hard rule for
 * an SDK that installs into CMS modules on shared hosting, where every transitive package
 * is a support ticket — a rule worth a failing test rather than a line in RULES.md.
 */
final class ComposerRequirementsTest extends TestCase
{
    /**
     * Function-name prefix => the extension that provides it.
     *
     * Only extensions a build can actually drop are listed. `pcre`, `filter`, `hash`,
     * `json` on PHP 8 and the standard library cannot be disabled on the supported range
     * (`>=7.4`), so requiring a declaration for `preg_match()`, `filter_var()`,
     * `hash_equals()` or `inet_pton()` would be noise rather than a guard.
     *
     * @var array<string, string>
     */
    private const DISABLEABLE_EXTENSIONS = [
        'bcadd'             => 'bcmath',
        'ctype_'            => 'ctype',
        'curl_'             => 'curl',
        'finfo_'            => 'fileinfo',
        'gmp_'              => 'gmp',
        'iconv'             => 'iconv',
        'json_'             => 'json',
        'mb_'               => 'mbstring',
        'mime_content_type' => 'fileinfo',
        'openssl_'          => 'openssl',
        'simplexml_'        => 'simplexml',
    ];

    /**
     * Every extension whose functions appear in `src/` is named in the manifest: in
     * `require` when the SDK cannot work without it, or in `suggest` when the call sits
     * behind a `function_exists()` guard — which is exactly and only `ext-curl`, because
     * `CurlTransport` falls back to `file_get_contents()`.
     */
    public function testEveryExtensionUsedInSrcIsDeclared(): void
    {
        $required = $this->extensionNames($this->manifestSection('require'));
        $suggested = $this->extensionNames($this->manifestSection('suggest'));
        $declared = array_merge($required, $suggested);

        $usages = $this->extensionUsagesInSrc();
        $this->assertNotSame([], $usages, 'Scanning src/ found no extension calls at all — the scanner is broken, not the manifest.');

        foreach ($usages as $extension => $evidence) {
            $this->assertContains(
                $extension,
                $declared,
                sprintf(
                    'src/ uses %s, so composer.json must declare "ext-%s" in require '
                    . '(or in suggest when every call is guarded by function_exists()).',
                    $evidence,
                    $extension
                )
            );
        }

        $this->assertContains('curl', $suggested, 'ext-curl belongs in suggest: the cURL transport is optional.');
        $this->assertNotContains(
            'curl',
            $required,
            'ext-curl must not become a hard requirement — CurlTransport keeps a file_get_contents() fallback so the SDK installs where cURL is absent.'
        );
    }

    /**
     * `require` holds only `php` and `ext-*`. Zero Composer packages at runtime is the
     * stance that keeps the PHP floor at 7.4 and the install friction at nothing; the
     * benchmark in .ai-factory/references/ shows what the alternative costs.
     */
    public function testRequireContainsNoComposerPackages(): void
    {
        foreach (array_keys($this->manifestSection('require')) as $requirement) {
            $requirement = (string) $requirement;

            $this->assertTrue(
                $requirement === 'php' || strpos($requirement, 'ext-') === 0,
                sprintf('composer.json require must hold only "php" and "ext-*" entries, found "%s".', $requirement)
            );
        }
    }

    /**
     * @return array<mixed> the named top-level object from composer.json, or [] when absent
     */
    private function manifestSection(string $section): array
    {
        $raw = file_get_contents(dirname(__DIR__) . '/composer.json');
        $this->assertIsString($raw, 'composer.json must be readable.');

        $decoded = json_decode((string) $raw, true);
        $manifest = is_array($decoded) ? $decoded : [];
        $this->assertNotSame([], $manifest, 'composer.json must decode to a non-empty JSON object.');

        return isset($manifest[$section]) && is_array($manifest[$section]) ? $manifest[$section] : [];
    }

    /**
     * Extension names, `ext-` prefix stripped, from one manifest section.
     *
     * @param array<mixed> $section
     *
     * @return string[]
     */
    private function extensionNames(array $section): array
    {
        $names = [];

        foreach (array_keys($section) as $key) {
            $key = (string) $key;

            if (strpos($key, 'ext-') === 0) {
                $names[] = substr($key, 4);
            }
        }

        return $names;
    }

    /**
     * Extensions the code in `src/` actually reaches for, with the call that proves it.
     *
     * @return array<string, string> extension name => "function() in src/Path/File.php"
     */
    private function extensionUsagesInSrc(): array
    {
        $root = dirname(__DIR__);
        $usages = [];

        foreach ($this->phpFilesIn($root . '/src') as $path) {
            $code = file_get_contents($path);

            if (!is_string($code)) {
                continue;
            }

            foreach ($this->functionCallsIn($code) as $function) {
                $extension = $this->extensionProviding($function);

                if ($extension === null || isset($usages[$extension])) {
                    continue;
                }

                $usages[$extension] = sprintf('%s() in %s', $function, str_replace($root . '/', '', $path));
            }
        }

        ksort($usages);

        return $usages;
    }

    /**
     * @return string[] absolute paths, sorted so the reported evidence is stable
     */
    private function phpFilesIn(string $directory): array
    {
        $paths = [];
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                $paths[] = $file->getPathname();
            }
        }

        sort($paths);

        return $paths;
    }

    /**
     * Bare function names referenced by the source, via the tokenizer rather than a regex
     * so that comments and string literals cannot fake a dependency — `function_exists(
     * 'curl_init')` must not count on its own, and a docblock naming `mb_strlen()` must
     * not demand ext-mbstring. Method calls and declarations are skipped for the same
     * reason.
     *
     * @return string[]
     */
    private function functionCallsIn(string $code): array
    {
        $calls = [];
        $tokens = token_get_all($code);
        $skipAfter = [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_CONST];
        $previous = null;

        foreach ($tokens as $token) {
            if (!is_array($token)) {
                if (trim($token) !== '') {
                    $previous = null;
                }

                continue;
            }

            if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            if ($token[0] === T_STRING && !in_array($previous, $skipAfter, true)) {
                $calls[] = strtolower($token[1]);
            }

            $previous = $token[0];
        }

        return $calls;
    }

    private function extensionProviding(string $function): ?string
    {
        foreach (self::DISABLEABLE_EXTENSIONS as $prefix => $extension) {
            if (strpos($function, $prefix) === 0) {
                return $extension;
            }
        }

        return null;
    }
}
