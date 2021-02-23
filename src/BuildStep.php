<?php


namespace App;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Process;

class BuildStep
{
    /** @var string  */
    protected $name;
    /** @var array  */
    protected $config;
    /** @var BuildStep|null */
    protected $parent;
    /** @var SymfonyStyle */
    private $io;

    public function __construct(string $name, array $config, SymfonyStyle $io)
    {
        $this->name = $name;
        $this->config = $config;
        $this->io = $io;
    }

    public function dependsOn(BuildStep $parent)
    {
        $this->parent = $parent;
    }

    public function hash()
    {
        if (isset($this->config['commit']) && isset($this->config['commit']['branch']) && isset($this->config['commit']['repo'])) {
            $branch = $this->config['commit']['branch'];
            $repo = $this->config['commit']['repo'];
            $process = new Process('git ls-remote ' . $repo . ' ' . $branch);
            $process->run();
            $hash = substr($process->getOutput(), 0, 40);
        } elseif (isset($this->config['files'])) {
            $result = '';
            foreach ($this->config['files'] as $input) {
                $path = getcwd() . '/' . $input;
                if (!file_exists($path)) {
                    $this->io->warning('File ' . $input . ' does not exist!');
                    continue;
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
            $hash = md5($result);
        } else {
            $hash = md5(uniqid());
        }
        if ($this->parent instanceof BuildStep) {
            $hash = md5($hash . $this->parent->hash());
        }
        return $hash;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function title(): string
    {
        return $this->config['title'] ?? $this->name;
    }

    public function clearBeforeBuild(): bool
    {
        if (isset($this->config['clear']) &&
            $this->config['clear'] &&
            isset($this->config['output']) &&
            is_dir($this->config['output'])) {

            $dir = realpath($this->config['output']);
            if (!$dir) {
                echo "Not today.";
                return false;
            }

            $emptyDirectoryProcess = new Process("rm -rf $dir/*");
            $emptyDirectoryProcess->run();
            return true;
        }

        return false;
    }

    public function build(): bool
    {
        $process = new Process($this->config['cmd']);
        $timeout = $this->config['timeout'] ?? 36000;
        $process->setTimeout($timeout);
        if (isset($this->config['working_dir']) && is_dir($this->config['working_dir'])) {
            $dir = realpath($this->config['working_dir']);
            $this->io->writeln('Set working dir to ' . $dir);
            $process->setWorkingDirectory($dir);
        }
        $exitCode = $process->run(function($type, $buffer) {
            $this->io->write($buffer);
        });
        return $exitCode === 0;
    }

    public function registeredHash(): ?string
    {
        $file = getcwd() . '/' . $this->config['output'] . '/sbs.built.json';
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode(file_get_contents($file), true);
        return $data[$this->name] ?? null;
    }

    public function registerHash(string $hash)
    {
        $file = getcwd() . '/' . $this->config['output'] . '/sbs.built.json';
        $data = [];
        if (is_file($file)) {
            $data = json_decode(file_get_contents($file), true);
        }
        $data[$this->name] = $hash;
        file_put_contents($file, json_encode($data));
    }

}
