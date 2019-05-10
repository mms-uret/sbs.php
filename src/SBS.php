<?php


namespace App;


use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

class SBS
{
    private $rootDirectory;
    private $io;
    private $buildRegistry;

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
        $finder = new Finder();
        $result = [];
        foreach ($finder->directories()->sortByName()->depth(0)->in($this->rootDirectory . '/sbs') as $dir) {
            $buildStep = new BuildStep($dir, $this->io);
            $result[$buildStep->name()] = $buildStep;
        }

        // resolve depends_on
        foreach ($result as $step) {
            /** @var BuildStep $step */
            $dependsOn = $step->dependsOn();
            if ($dependsOn) {
                $step->setParent($result[$dependsOn]);
            }
        }
        return $result;
    }

    public function buildRegistry(string $filePath)
    {
        $this->buildRegistry = $filePath;
    }

    public function isIncrement(string $name, string $hash): bool
    {
        if (!$this->buildRegistry || !is_file($this->buildRegistry)) {
            return true;
        }
        $alreadyBuilt = json_decode(file_get_contents($this->buildRegistry), true);
        return !($alreadyBuilt && isset($alreadyBuilt[$name]) && $alreadyBuilt[$name] === $hash);
    }

    public function registerBuild(string $name, string $hash)
    {
        if (!$this->buildRegistry) {
            return;
        }
        if (is_file($this->buildRegistry)) {
            $alreadyBuilt = json_decode(file_get_contents($this->buildRegistry), true);
        } else {
            $alreadyBuilt = [];
        }
        $alreadyBuilt[$name] = $hash;
        file_put_contents($this->buildRegistry, json_encode($alreadyBuilt));
    }

    public function builtNames(string $builtFilePath): array
    {
        if (is_file($builtFilePath)) {
            return json_decode($builtFilePath, true);
        } else {
            return [];
        }
    }
}