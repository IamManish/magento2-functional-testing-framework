<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\FunctionalTestingFramework\Util;

/**
 * Class ModulePathExtractor, resolve module reference based on path
 */
class ModulePathExtractor
{
    const SPLIT_DELIMITER = '_';

    /**
     * Test module paths
     *
     * @var array
     */
    private $testModulePaths = [];

    /**
     * ModulePathExtractor instance
     *
     * @var ModulePathExtractor
     */
    private static $instance = null;

    /**
     * Get ModulePathExtractor instance
     *
     * @return ModulePathExtractor
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new ModulePathExtractor();
        }
        return self::$instance;
    }

    /**
     * ModulePathExtractor constructor
     */
    private function __construct()
    {
        if (empty($this->testModulePaths)) {
            $this->testModulePaths = ModuleResolver::getInstance()->getModulesPath(false);
        }
    }

    /**
     * Extracts module name from the path given
     *
     * @param string $path
     * @return string
     */
    public function extractModuleName($path)
    {
        $key = $this->extractKeyByPath($path);
        if (empty($key)) {
            return "NO MODULE DETECTED";
        }
        $parts = $this->splitKeyForParts($key);
        return isset($parts[1]) ? $parts[1] : "NO MODULE DETECTED";
    }

    /**
     * Extracts vendor name for module from the path given
     *
     * @param string $path
     * @return string
     */
    public function getExtensionPath($path)
    {
        $key = $this->extractKeyByPath($path);
        if (empty($key)) {
            return "NO VENDOR DETECTED";
        }
        $parts = $this->splitKeyForParts($key);
        return isset($parts[0]) ? $parts[0] : "NO VENDOR DETECTED";
    }

    /**
     * Split key by SPLIT_DELIMITER and return parts array
     *
     * @param string $key
     * @return array
     */
    private function splitKeyForParts($key)
    {
        $parts = explode(self::SPLIT_DELIMITER, $key);
        return count($parts) == 2 ? $parts : [];
    }

    /**
     * Extract module name key by path
     *
     * @param string $path
     * @return string
     */
    private function extractKeyByPath($path)
    {
        if (empty($path)) {
            return '';
        }
        $paths = explode(DIRECTORY_SEPARATOR, $path);
        if (count($paths) < 3) {
            return '';
        }
        $paths = array_slice($paths, 0, count($paths)-2);
        $shortenedPath = implode(DIRECTORY_SEPARATOR, $paths);
        foreach ($this->testModulePaths as $key => $pathArr) {
            if (isset($pathArr[0]) && $pathArr[0] == $shortenedPath) {
                return $key;
            }
        }
        return '';
    }
}
