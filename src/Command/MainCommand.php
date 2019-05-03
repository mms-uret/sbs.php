<?php


namespace App\Command;


use App\SBS;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MainCommand extends Command
{
    protected function configure()
    {
        $this->setName('main');
        $this->addArgument('step', InputArgument::OPTIONAL, 'which build step to build. if not specified, all found all built');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $sbs = new SBS(getcwd(), $io);

        $steps = $sbs->list();
        $io->writeln('Found ' . count($steps) . ' build steps');

        // for now we take the first one. when this works we will loop through all steps or just execute
        // the one specified by $input->getArgument('step')
        $step = $steps[0];

        $io->title($step->title());

        $hash = $step->hash();
        $io->writeln('Hash: ' . $hash);

        if ($step->hasDockerImage($hash)) {
            $io->writeln('There is a Docker image for this hash.');
        } else {
            $io->writeln("There is no Docker image for this hash, so we're building one.");
            $io->section('Build');
            $step->build();
        }

        $io->section('Copy build artifacts to your code');
        $step->run();

        $io->success('Done \\o/');
    }

}