<?php

namespace SebastianBergmann\PHPCPD\Detector\Strategy\CachedTokens;

/**
 * Cached token strategy for detecting code clones.
 *
 * @author    Eugene Vrann Tulika <vranen@gmail.com>
 */
class Loader
{
    private $tokens = [];

    private $hash = [];

    private $directory = [];

    private function getTokensCache()
    {
        if (!file_exists('cache_index.token')) {
            echo 'index';
            $dir_iterator = new \RecursiveDirectoryIterator('~/Projects/php/compare_extensions');
            $iterator = new \RecursiveIteratorIterator($dir_iterator, \RecursiveIteratorIterator::CHILD_FIRST);
            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {

                if (strpos($file->getFilename(), '.php') !== false
                    && strpos($file->getFilename(), '.php') == strlen($file->getFilename()) - 4
                ) {
                    $this->reindex($file->getPathname());
                }
            }
            file_put_contents('cache_index.token', serialize($this->hash));
            file_put_contents('cache_index.directory', serialize($this->directory));
        } else {
            echo 'read';
            $this->hash = unserialize(file_get_contents('cache_index.token'));
            $this->directory = unserialize(file_get_contents('cache_index.directory'));
        }
    }

    public function getHashes()
    {
        if (empty($this->hash)) {
            $this->getTokensCache();
        }
        return $this->hash;
    }

    public function getDirectory()
    {
        if (empty($this->directory)) {
            $this->getTokensCache();
        }
        return $this->directory;
    }

    public function reindex($file, $fuzzy = true, $minTokens = 70)
    {
        $buffer                    = file_get_contents($file);
        $currentSignature          = '';
        $tokens                    = token_get_all($buffer);
        $tokenNr                   = 0;

        unset($buffer);

        foreach (array_keys($tokens) as $key) {
            $token = $tokens[$key];

            if (is_array($token)) {
                if ($fuzzy && $token[0] == T_VARIABLE) {
                    $token[1] = 'variable';
                }

                $currentSignature .= chr($token[0] & 255) .
                    pack('N*', crc32($token[1]));

                $tokenNr++;
            }

        }

        $this->directory[] = $file;
        for ($j = 0; $j < strlen($currentSignature) / 5 - $minTokens; $j++) {
            $key = md5(substr($currentSignature, $j * 5, $minTokens * 5));
            if (isset($this->hash[$key])) {
                if (is_array($this->hash[$key])) {
                    $this->hash[$key][] = count($this->directory) - 1;
                } else {
                    $this->hash[$key] = [count($this->directory) - 1, $this->hash[$key]];
                }
            } else {
                $this->hash[$key] = count($this->directory) - 1;
            }
        }

        file_put_contents($file . '.token', $currentSignature);
    }
}
