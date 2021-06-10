<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Flex;

use Composer\Package\Version\VersionParser;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class PackageResolver
{
    private static $aliases;
    private static $versions;

    private $downloader;

    public function __construct(Downloader $downloader)
    {
        $this->downloader = $downloader;
    }

    public function resolve(array $arguments = [])
    {
        $versionParser = new VersionParser();

        // first pass split on : and = to separate package names and versions
        $explodedArguments = [];
        foreach ($arguments as $argument) {
            if ((false !== $pos = strpos($argument, ':')) || (false !== $pos = strpos($argument, '='))) {
                $explodedArguments[] = substr($argument, 0, $pos);
                $explodedArguments[] = substr($argument, $pos + 1);
            } else {
                $explodedArguments[] = $argument;
            }
        }

        // second pass to resolve package names
        $packages = [];
        foreach ($explodedArguments as $argument) {
            if (false === strpos($argument, '/')) {
                if (null === self::$aliases) {
                    self::$aliases = $this->downloader->getContents('/aliases.json');
                }

                if (isset(self::$aliases[$argument])) {
                    $argument = self::$aliases[$argument];
                } else {
                    // is it a version or an alias that does not exist?
                    try {
                        $versionParser->parseConstraints($argument);
                    } catch (\UnexpectedValueException $e) {
                        $this->throwAlternatives($argument);
                    }
                }
            }

            $packages[] = $argument;
        }

        // third pass to resolve versions
        $requires= [];
        foreach ($versionParser->parseNameVersionPairs($packages) as $package) {
            $requires[] = $package['name'].$this->parseVersion($package['name'], $package['version'] ?? null);
        }

        return array_unique($requires);
    }

    private function parseVersion($package, $version)
    {
        if (!$version) {
            return '';
        }

        if (null === self::$versions) {
            self::$versions = $this->downloader->getContents('/versions.json');
        }

        if (!isset(self::$versions['splits'][$package])) {
            return ':'.$version;
        }

        if ('next' === $version) {
            $version = '^'.self::$versions[$version].'@dev';
        } elseif (in_array($version, ['lts', 'previous', 'stable'])) {
            $version = '^'.self::$versions[$version];
        }

        return ':'.$version;
    }

    private function throwAlternatives($argument)
    {
        $alternatives = [];
        foreach (self::$aliases as $alias => $package) {
            $lev = levenshtein($argument, $alias);
            if ($lev <= strlen($argument) / 3 || false !== strpos($alias, $argument)) {
                $alternatives[$package][] = $alias;
            }
        }

        if ($alternatives) {
            $message = sprintf('"%s" is not a valid alias. ', $argument);
            if (1 == count($alternatives)) {
                $message .= "Did you mean this:\n";
            } else {
                $message .= "Did you mean one of these:\n";
            }
            foreach ($alternatives as $package => $aliases) {
                $message .= sprintf("  \"%s\", supported aliases: \"%s\"\n", $package, implode('", "', $aliases));
            }
        } else {
            $message = sprintf('Could not parse version constraint "%s".', $argument);
        }

        throw new \UnexpectedValueException($message);
    }
}
