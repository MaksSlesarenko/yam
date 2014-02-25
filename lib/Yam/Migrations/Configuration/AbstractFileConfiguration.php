<?php

namespace Yam\Migrations\Configuration;

use Yam\Migrations\Exception\MigrationException;

abstract class AbstractFileConfiguration extends Configuration
{
    /**
     * The configuration file used to load configuration information
     *
     * @var string
     */
    private $file;

    /**
     * Whether or not the configuration file has been loaded yet or not
     *
     * @var boolean
     */
    private $loaded = false;

    /**
     * Load the information from the passed configuration file
     *
     * @param string $file The path to the configuration file
     *
     * @throws MigrationException Throws exception if configuration file was already loaded
     */
    public function load($file)
    {
        if ($this->loaded) {
            throw MigrationException::configurationFileAlreadyLoaded();
        }
        if (file_exists($path = getcwd() . '/' . $file)) {
            $file = $path;
        }
        $this->file = $file;
        $this->doLoad($file);
        $this->loaded = true;
    }

    protected function getDirectoryRelativeToFile($file, $input)
    {
        $path = realpath(dirname($file) . '/' . $input);
        if ($path !== false) {
            $directory = $path;
        } else {
            $directory = $input;
        }

        return $directory;
    }

    public function getFile()
    {
        return $this->file;
    }

    /**
     * Abstract method that each file configuration driver must implement to
     * load the given configuration file whether it be xml, yaml, etc. or something
     * else.
     *
     * @param string $file The path to a configuration file.
     */
    abstract protected function doLoad($file);
}
