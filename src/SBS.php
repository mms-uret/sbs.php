<?php


namespace App;


use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
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

        $result = [];
        foreach ($config as $name => $stepConfig) {
            $stepConfig = $this->resolveConfig($name, $stepConfig);
            $result[$name] = new BuildStep($name, $stepConfig, $this->io);
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

    protected function resolveConfig(string $name, array $config): array
    {
        $resolver = new OptionsResolver();
        $resolver->setRequired('name')->setAllowedTypes('name', 'string');
        $resolver->setDefault('title', function(Options $options) {
            return $options['name'];
        })->setAllowedTypes('title', 'string');
        $resolver->setRequired('cmd')->setAllowedTypes('cmd', 'string');
        $resolver->setDefault('timeout', 3600)->setAllowedTypes('timeout', 'int');
        $resolver->setDefault('working_dir', '')->setAllowedTypes('working_dir', 'string');

        $resolver->setRequired('output')->setAllowedTypes('output', 'string');

        $resolver->setDefault('files', [])->setAllowedTypes('files', 'string[]');
        $resolver->setDefault('commit', function(OptionsResolver $commitResolver) {
            $commitResolver->setDefaults([
                'repo' => '',
                'branch' => ''
            ]);
        });

        $config['name'] = $name;
        return $resolver->resolve($config);
    }

}