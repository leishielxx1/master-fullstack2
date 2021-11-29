<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Flex\Configurator;

use Symfony\Flex\Recipe;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class EnvConfigurator extends AbstractConfigurator
{
    public function configure(Recipe $recipe, $vars): void
    {
        $this->write('Adding environment variable defaults');

        $this->configureEnvDist($recipe, $vars);
        $this->configurePhpUnit($recipe, $vars);
    }

    public function unconfigure(Recipe $recipe, $vars): void
    {
        $this->unconfigureEnvFiles($recipe, $vars);
        $this->unconfigurePhpUnit($recipe, $vars);
    }

    private function configureEnvDist(Recipe $recipe, $vars): void
    {
        $distenv = getcwd().'/.env.dist';
        if ($this->isFileMarked($recipe, $distenv)) {
            return;
        }

        $data = '';
        foreach ($vars as $key => $value) {
            if ('%generate(secret)%' === $value) {
                $value = bin2hex(random_bytes(16));
            }
            if ('#' === $key[0]) {
                $data .= '# '.$value."\n";
            } else {
                $value = $this->options->expandTargetDir($value);
                $data .= "$key=$value\n";
            }
        }
        if (!file_exists(getcwd().'/.env')) {
            copy($distenv, getcwd().'/.env');
        }
        $data = $this->markData($recipe, $data);
        file_put_contents($distenv, $data, FILE_APPEND);
        file_put_contents(getcwd().'/.env', $data, FILE_APPEND);
    }

    private function configurePhpUnit(Recipe $recipe, $vars): void
    {
        foreach (['phpunit.xml.dist', 'phpunit.xml'] as $file) {
            $phpunit = getcwd().'/'.$file;
            if (!is_file($phpunit)) {
                continue;
            }

            if ($this->isFileXmlMarked($recipe, $phpunit)) {
                continue;
            }

            $data = '';
            foreach ($vars as $key => $value) {
                if ('%generate(secret)%' === $value) {
                    $value = bin2hex(random_bytes(16));
                }
                if ('#' === $key[0]) {
                    $data .= '        <!-- '.$value." -->\n";
                } else {
                    $value = $this->options->expandTargetDir($value);
                    $data .= "        <env name=\"$key\" value=\"$value\" />\n";
                }
            }
            $data = $this->markXmlData($recipe, $data);
            file_put_contents($phpunit, preg_replace('{^(\s+</php>)}m', $data.'$1', file_get_contents($phpunit)));
        }
    }

    private function unconfigureEnvFiles(Recipe $recipe, $vars): void
    {
        foreach (['.env', '.env.dist'] as $file) {
            $env = getcwd().'/'.$file;
            if (!file_exists($env)) {
                continue;
            }

            $contents = preg_replace(sprintf('{%s*###> %s ###.*###< %s ###%s+}s', "\n", $recipe->getName(), $recipe->getName(), "\n"), "\n", file_get_contents($env), -1, $count);
            if (!$count) {
                continue;
            }

            $this->write(sprintf('Removing environment variables from %s', $file));
            file_put_contents($env, $contents);
        }
    }

    private function unconfigurePhpUnit(Recipe $recipe, $vars): void
    {
        foreach (['phpunit.xml.dist', 'phpunit.xml'] as $file) {
            $phpunit = getcwd().'/'.$file;
            if (!is_file($phpunit)) {
                continue;
            }

            $contents = preg_replace(sprintf('{%s*\s+<!-- ###\+ %s ### -->.*<!-- ###- %s ### -->%s+}s', "\n", $recipe->getName(), $recipe->getName(), "\n"), "\n", file_get_contents($phpunit), -1, $count);
            if (!$count) {
                continue;
            }

            $this->write(sprintf('Removing environment variables from %s', $file));
            file_put_contents($phpunit, $contents);
        }
    }
}
