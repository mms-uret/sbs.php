<?php


namespace App\Command;


use App\SBS;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InitCommand extends Command
{
    protected function configure()
    {
        $this->setName('init');
        $this->setDescription('Creates a basic sbs.yml which contains a composer build step to get you started');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $sbs = new SBS(getcwd(), $io);
        $sbs->init();
    }

}