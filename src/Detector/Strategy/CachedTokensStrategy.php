<?php
/*
 * This file is part of PHP Copy/Paste Detector (PHPCPD).
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SebastianBergmann\PHPCPD\Detector\Strategy;

use SebastianBergmann\PHPCPD\CodeClone;
use SebastianBergmann\PHPCPD\CodeCloneFile;
use SebastianBergmann\PHPCPD\CodeCloneMap;

/**
 * Cached token strategy for detecting code clones.
 *
 * @author    Eugene Vrann Tulika <vranen@gmail.com>
 */
class CachedTokensStrategy extends AbstractStrategy
{
    private $tokenLoader;

    public function __construct(CachedTokens\Loader $tokenLoader)
    {
        $this->tokenLoader = $tokenLoader;
    }

    /**
     * Copy & Paste Detection (CPD).
     *
     * @param  string       $file
     * @param  integer      $minLines
     * @param  integer      $minTokens
     * @param  CodeCloneMap $result
     * @param  boolean      $fuzzy
     * @author Eugene Vrann Tulika <vranen@gmail.com>
     */
    public function processFile($file, $minLines, $minTokens, CodeCloneMap $result, $fuzzy = false)
    {
        $buffer  = file_get_contents($file);
        $currentSignature = $this->reindex($buffer);
        $result->setNumLines(
            $result->getNumLines() + substr_count($buffer, "\n")
        );
        unset($buffer);

        $duplicates = $this->getLongestSubSequence($currentSignature, $this->tokenLoader->getHashes(), $minTokens);

        if (count($duplicates) > 0) {
            foreach ($duplicates as $fileIndex => $count) {
                $result->addClone(
                    new CodeClone(
                        new CodeCloneFile($file, 1),
                        new CodeCloneFile($this->tokenLoader->getDirectory()[$fileIndex], 1),
                        $count,
                        10
                    )
                );

            }
        }
    }

    private function getLongestSubSequence($str1, $hash, $minTokens)
    {
        $duplicates = [];


        for ($i = 0; $i < strlen($str1) / 5 - $minTokens; $i++) {
            $key = md5(substr($str1, $i * 5, $minTokens * 5));
            if (isset($hash[$key])) {
                if (is_array($hash[$key])) {
                    foreach ($hash[$key] as $fileIndex) {
                        $duplicates[$fileIndex] = 1;
                    }
                } else {
                    $duplicates[$hash[$key]] = 1;
                }
            }
        }


        return $duplicates;
    }

    public function reindex($string, $fuzzy = true)
    {
        $currentSignature          = '';
        $tokens                    = token_get_all($string);

        unset($string);

        foreach (array_keys($tokens) as $key) {
            $token = $tokens[$key];

            if (is_array($token)) {
                if ($fuzzy && $token[0] == T_VARIABLE) {
                    $token[1] = 'variable';
                }

                $currentSignature .= chr($token[0] & 255) .
                    pack('N*', crc32($token[1]));
            }

        }
        return $currentSignature;
    }
}
