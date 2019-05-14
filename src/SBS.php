<?php


namespace App;


use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

class SBS
{
    private $rootDirectory;
    private $io;

    public function __construct($rootDirectory, SymfonyStyle $io)
    {
        $this->rootDirectory = $rootDirectory;
        $this->io = $io;
    }

    /**
     * @return BuildStep[]
     */
    public function list(): array
    {
        $file = $this->rootDirectory . '/sbs.yml';
        if (!is_file($file)) {
            $this->io->error('No sbs.yml file found in current directory');
        }
        $config = Yaml::parseFile($file);
        // TODO: validate $config
        $result = [];
        foreach ($config as $name => $stepConfig) {
            $result[$name] = new BuildStep($name, $stepConfig, $this->io);
        }

        return $result;
    }

}