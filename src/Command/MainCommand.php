<?php


namespace App\Command;


use App\BuildStep;
use App\SBS;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MainCommand extends Command
{
    protected function configure()
    {
        $this->setName('main');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sbs = new SBS();

        $steps = $sbs->list();
        $io = new SymfonyStyle($input, $output);
        $io->listing(array_map(function (BuildStep $buildStep) {
            return $buildStep->name() . ': ' . $buildStep->hasDockerImage($buildStep->hash());
        }, $steps));
    }

}