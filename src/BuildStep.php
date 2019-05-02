<?php


namespace App;


use Symfony\Component\Yaml\Yaml;

class BuildStep
{
    protected $directory;
    protected $config;

    public function __construct(string $directory)
    {
        $this->directory = $directory;
        $this->config = Yaml::parseFile($this->directory . '/sbs.yml');
    }

    public function hash()
    {
        $result = '';
        foreach ($this->config['input'] as $input) {
            $path = $this->directory . '/../../' . $input;
            if (!file_exists($path)) {
                throw new \Exception('Should be here: ' . $path);
            }
            $result .= md5_file($path);
        }
        return md5($result);
    }

    public function hasDockerImage(string $hash): string
    {
        return "Would check for: " . $this->config['image'] . ':' . $hash;
    }

    public function build()
    {
        // copy files needed for build
        // copy Dockerfile
        // start docker build
        // push container to docker hub
    }

    public function run()
    {
        // run docker container
    }

    public function name()
    {
        return basename($this->directory);
    }

    public function __toString()
    {
        return $this->name();
    }
}