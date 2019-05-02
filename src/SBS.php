<?php


namespace App;


use Symfony\Component\Finder\Finder;

class SBS
{
    private $rootDirectory;

    public function __construct($rootDirectory = null)
    {
        if (!$rootDirectory) {
            $rootDirectory = getcwd();
        }
        $this->rootDirectory = $rootDirectory;
        if (!is_dir($this->rootDirectory)) {
            throw new \Exception('Need sbs directory!!!');
        }
    }

    /**
     * @return BuildStep[]
     */
    public function list(): array
    {
        $finder = new Finder();
        $result = [];
        foreach ($finder->directories()->depth(0)->in($this->rootDirectory . '/sbs') as $dir) {
            $result[] = new BuildStep($dir);
        }
        return $result;
    }
}