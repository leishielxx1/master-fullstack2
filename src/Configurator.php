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

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\Package;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Configurator
{
    private $composer;
    private $io;
    private $options;
    private $configurators;
    private $cache;

    public function __construct(Composer $composer, IOInterface $io, Options $options)
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->options = $options;
        $this->configurators = [
            'bundles' => Configurator\BundlesConfigurator::class,
            'composer-scripts' => Configurator\ComposerScriptsConfigurator::class,
            'copy-from-recipe' => Configurator\CopyFromRecipeConfigurator::class,
            'copy-from-package' => Configurator\CopyFromPackageConfigurator::class,
            'env' => Configurator\EnvConfigurator::class,
            'container' => Configurator\ContainerConfigurator::class,
            'makefile' => Configurator\MakefileConfigurator::class,
        ];
    }

    public function install(Recipe $recipe)
    {
        foreach ($recipe->getManifest() as $key => $config) {
            $this->get($key)->configure($recipe, $config);
        }
    }

    public function unconfigure(Recipe $recipe)
    {
        foreach ($recipe->getManifest() as $key => $config) {
            $this->get($key)->unconfigure($recipe, $config);
        }
    }

    /**
     * @return Configurator\AbstractConfigurator
     */
    private function get($key)
    {
        if (!isset($this->configurators[$key])) {
            throw new \InvalidArgumentException(sprintf('Unknown configurator "%s".', $key));
        }

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $class = $this->configurators[$key];

        return $this->cache[$key] = new $class($this->composer, $this->io, $this->options);
    }
}
