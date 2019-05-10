<?php


namespace App\Command;


use App\BuildStep;
use App\SBS;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BuildCommand extends Command
{
    protected function configure()
    {
        $this->setName('build');
        $this->addArgument('step', InputArgument::OPTIONAL, 'which build step to build. if not specified, all found all built');
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'When given, the container will be built anyways');
        $this->addOption('increment', 'i', InputOption::VALUE_REQUIRED, 'File which holds the hashes which are currently built and do not need a rebuild');

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $sbs = new SBS(getcwd(), $io);

        $steps = $sbs->list();
        $io->writeln('Found ' . count($steps) . ' build steps');

        $givenStep = $input->getArgument('step');
        if ($givenStep) {
            $steps = array_filter($steps, function (BuildStep $step) use ($givenStep) {
                return $step->name() === $givenStep;
            });
        }

        $buildRegistry = $input->getOption('increment');
        if ($buildRegistry) {
            $sbs->buildRegistry($buildRegistry);
        }

        foreach ($steps as $step) {
            $io->title($step->title());

            $hash = $step->hash();
            $io->writeln('Hash: ' . $hash);

            if (!$sbs->isIncrement($step->name(), $hash)) {
                $io->writeln('Hash is already built according to built registry. So we are skipping this step.');
                continue;
            }

            if ($step->hasCommand()) {
                $step->execute();
            } else {
                if (!$input->hasOption('force') && $step->hasDockerImage($hash)) {
                    $io->writeln('There is a Docker image for this hash.');
                } else {
                    $io->writeln("There is no Docker image for this hash, so we're building one.");
                    $io->section('Build');
                    $step->build();
                }

                $io->section('Copy build artifacts to your code');
                $step->run();
            }

            $sbs->registerBuild($step->name(), $hash);
        }

        $io->success('Done \\o/');
    }

}