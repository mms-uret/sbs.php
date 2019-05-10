<?php


namespace App;


use Exception;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class BuildStep
{
    protected $directory;
    protected $config;
    protected $projectDirectory;
    private $io;
    /** @var BuildStep */
    private $parent;

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
            if (is_dir($path)) {
                $finder = new Finder();
                foreach ($finder->files()->in($path) as $file) {
                    /** @var SplFileInfo $file */
                    $result .= md5_file($file->getRealPath());
                }
            } else {
                $result .= md5_file($path);
            }
        }
        if ($this->parent) {
            $result .= $this->parent->hash();
        }
        return md5($result);
    }

    public function hasDockerImage(string $hash): bool
    {
        $cmd = "docker image pull " . $this->config['image'] . ':' . $hash;
        $process = Process::fromShellCommandline($cmd);
        return $this->executeProcess($process);
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
        $this->io->writeln('Prepare input for container');
        foreach ($this->config['input'] as $input) {
            $cmd = 'cp -R ' . $this->projectDirectory . '/' . $input . ' ' . $this->directory . '/input';
            $process = Process::fromShellCommandline($cmd);
            $this->executeProcess($process);
        }

        if ($this->parent) {
            $baseImage = $this->parent->dockerImage();
        } else {
            $baseImage = $this->config['base'];
        }

        // start docker build
        $imageName = $this->config['image'];
        $this->io->writeln("Start building docker image...");
        $cmd = 'docker build --rm -t ' . $imageName . ':' . $this->hash() . ' --build-arg base_image=' . $baseImage . ' ./';
        $process = Process::fromShellCommandline($cmd);
        $this->executeProcess($process, "Docker build");

        // push container to docker hub
        $this->io->writeln("Upload docker image to Docker Hub...");
        $cmd = "docker push " .  $imageName . ':' . $this->hash();
        $process = Process::fromShellCommandline($cmd);
        $this->executeProcess($process, "Publish image to docker hub");

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
        $cmd = 'docker rm -f ' . $name;
        $process = Process::fromShellCommandline($cmd);
        $this->executeProcess($process);

        $cmd = 'docker run --name ' . $name . ' -v ' . $this->projectDirectory . ':/local ' . $this->config['image'] . ':' . $this->hash();
        $process = Process::fromShellCommandline($cmd);
        $this->executeProcess($process, "Run Docker container");

        $cmd = 'docker rm -f ' . $name;
        $process = Process::fromShellCommandline($cmd);
        $this->executeProcess($process, "Remove docker container");
    }

    public function name(): string
    {
        return basename($this->directory);
    }

    public function title(): string
    {
        return $this->config['title'] ?? $this->name();
    }

    public function dependsOn(): string
    {
        return $this->config['depends_on'] ?? '';
    }

    public function setParent(BuildStep $parent)
    {
        $this->parent = $parent;
    }

    public function dockerImage(): string {
        return $this->config['image'] . ':' . $this->hash();
    }

    public function __toString()
    {
        return $this->name();
    }

    public function execute() {
        $process = Process::fromShellCommandline($this->config['cmd']);
        $process->setTimeout(36000);
        $exitCode = $process->run(function($type, $buffer) {
            $this->io->write($buffer);
        });
        return $exitCode === 0;
    }

    public function hasCommand(): bool
    {
        return isset($this->config['cmd']);
    }


    private function executeProcess(Process $process, $name = null)
    {
        $process->setWorkingDirectory($this->directory);
        $process->setTimeout(36000);
        $exitCode = $process->run(function($type, $buffer) use ($name) {
            if ($name) {
                $this->io->write($buffer);
            }
        });
        if ($name) {
            if ($exitCode === 0) {
                $this->io->success("Successful executed $name");
            } else {
                $this->io->error("Error in $name");
            }
        }
        return $exitCode === 0;
    }
}