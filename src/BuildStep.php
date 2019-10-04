<?php


namespace App;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Process;

class BuildStep
{
    protected $name;
    protected $config;
    private $io;


    public function __construct(string $name, array $config, SymfonyStyle $io)
    {
        $this->name = $name;
        $this->config = $config;
        $this->io = $io;
    }

    public function hash()
    {
        $result = '';
        if (isset($this->config['commit'])) {
            $branch = $this->config['commit']['branch'];
            $repo = $this->config['commit']['repo'];
            $process = Process::fromShellCommandline('git ls-remote ' . $repo . ' ' . $branch);
            $process->run();
            $result .= $process->getOutput();
        }
        if (isset($this->config['files'])) {
            foreach ($this->config['files'] as $input) {
                $path = getcwd() . '/' . $input;
                if (!file_exists($path)) {
                    throw new \Exception('Should be here: ' . $path);
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
        }
        if (!$result) {
            $result = uniqid();
        }
        return md5($result);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function title(): string
    {
        return $this->config['title'] ?? $this->name();
    }

    public function build(): bool
    {
        $process = Process::fromShellCommandline($this->config['cmd']);
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