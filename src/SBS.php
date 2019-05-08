<?php


namespace App;


use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

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
        $finder = new Finder();
        $result = [];
        foreach ($finder->directories()->depth(0)->in($this->rootDirectory . '/sbs') as $dir) {
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
}