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
            $this->io->error('No sbs.yml file found in current directory. Use init command');
            return [];
        }
        $config = Yaml::parseFile($file);

        /** @var BuildStep[] $result */
        $result = [];
        foreach ($config as $name => $stepConfig) {
            $this->checkConfig($name, $stepConfig);
            $result[$name] = new BuildStep($name, $stepConfig, $this->io);
        }

        foreach ($config as $name => $stepConfig) {
            if (isset($stepConfig['depends_on'])) {
                $parentName = $stepConfig['depends_on'];
                if (isset($result[$parentName])) {
                    $result[$name]->dependsOn($result[$parentName]);
                } else {
                    $this->io->warning("Build step " . $name . " depends on unknown step " . $parentName);
                }
            }
        }

        return $result;
    }

    public function init(): void
    {
        $file = $this->rootDirectory . '/sbs.yml';
        if (!is_file($file)) {
            $defaultFile = __DIR__ . '/../sbs.yml';
            $defaultContent = file_get_contents($defaultFile);
            file_put_contents($file, $defaultContent);

            $this->io->success('Created sbs.yml which describes your build steps. Please edit it accordingly.');
        } else {
            $this->io->success('You already have a sbs.yml! So nothing to do here.');
        }
    }

    public function checkConfig(string $name, array $config): void
    {
        if (!isset($config['cmd'])) {
            throw new \InvalidArgumentException("Build step $name is missing a command.");
        }
        if (!isset($config['output'])) {
            throw new \InvalidArgumentException("Build step $name is missing a directory where the output is written.");
        }
    }
}