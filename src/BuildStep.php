<?php


namespace App;


use Exception;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class BuildStep
{
    protected $directory;
    protected $config;
    protected $projectDirectory;
    private $io;

    public function __construct(string $directory, SymfonyStyle $io)
    {
        $this->directory = $directory;
        $this->projectDirectory = getcwd();
        $this->config = Yaml::parseFile($this->directory . '/sbs.yml');
        $this->io = $io;
    }

    public function hash()
    {
        $result = '';
        foreach ($this->config['input'] as $input) {
            $path = $this->projectDirectory . '/' . $input;
            if (!file_exists($path)) {
                throw new Exception('Should be here: ' . $path);
            }
            $result .= md5_file($path);
        }
        return md5($result);
    }

    public function hasDockerImage(string $hash): bool
    {
        // TODO
        return false;
    }

    public function build()
    {
        $filesystem = new Filesystem();

        // create move-output-when-needed
        $this->io->writeln("Create files for building docker image...");
        $content = "#!/bin/bash\n";
        foreach ($this->config['output'] as $output) {
            $content .= "rm -rf /local/" . $output . "\n";
            $content .= "mv /workspace/" . $output . " /local/\n";
        }
        file_put_contents($this->directory . '/move-output-when-needed', $content);

        // create build-output-hash
        $content = "#!/bin/bash\n";
        // this is empty for the moment until we found a good way to create a md5 hash over all output directories
        // in bash
        file_put_contents($this->directory . '/build-output-hash', $content);

        // copy Dockerfile
        $scriptsDirectory = realpath(__DIR__ . '/../scripts');
        $filesystem->copy($scriptsDirectory . '/Dockerfile', $this->directory . '/Dockerfile');

        // copy input to /input
        foreach ($this->config['input'] as $input) {
            $cmd = 'cp -R ' . $this->projectDirectory . '/' . $input . ' ' . $this->directory . '/input';
            $process = Process::fromShellCommandline($cmd);
            $this->executeProcess($process, "Prepare input files for docker container");
        }

        // start docker build
        $imageName = $this->config['image'];
        $this->io->writeln("Start building docker image...");
        $cmd = 'docker build --rm -t ' . $imageName . ':' . $this->hash() . ' --build-arg base_image=' . $this->config['base'] . ' ./';
        $this->io->confirm($cmd);
        $process = Process::fromShellCommandline($cmd);
        $this->executeProcess($process, "Docker build");

        // push container to docker hub
        $this->io->writeln("Upload docker image to Docker Hub...");
        // @claudio: da weiss ich nicht genau was zu tun ist

        // clean up
        $this->io->writeln("Clean up of build scripts...");
        $files = array_map(function ($baseName) {
            return $this->directory . '/' . $baseName;
        }, ['Dockerfile', 'move-output-when-needed', 'build-output-hash']);
        $filesystem->remove($files);
        foreach ($this->config['input'] as $input) {
            $filesystem->remove($this->directory . '/input/' . $input);
        }
    }

    public function run()
    {
        // run docker container
        $name = $this->name();
        $cmd = 'docker run --name ' . $name . ' -v ' . $this->projectDirectory . ':/local ' . $this->config['image'] . ':' . $this->hash();
        $this->io->confirm($cmd);
        $process = Process::fromShellCommandline($cmd);
        $this->executeProcess($process, "Run Docker container");

        $cmd = 'docker rm -f ' . $name;
        $this->io->confirm($cmd);
        $process = Process::fromShellCommandline($cmd);
        $this->executeProcess($process, "Remove docker container");
    }

    public function name()
    {
        return basename($this->directory);
    }

    public function title()
    {
        return $this->config['title'];
    }

    public function __toString()
    {
        return $this->name();
    }

    private function executeProcess(Process $process, $name)
    {
        $process->setWorkingDirectory($this->directory);
        $process->setTimeout(36000);
        $exitCode = $process->run(function($type, $buffer) {
            $this->io->write($buffer);
        });
        if ($exitCode === 0) {
            $this->io->success("Successful executed $name");
        } else {
            $this->io->error("Error in $name");
        }
    }
}