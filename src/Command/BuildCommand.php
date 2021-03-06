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
        $this->setDescription('Build the needed build steps defined in sbs.yml');
        $this->addArgument('step', InputArgument::OPTIONAL, 'which build step to build. if not specified, all found all built');
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'When given, the build steps will be built anyways');
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

        $successful = true;
        foreach ($steps as $step) {
            $startTime = new \DateTime();
            $io->title($step->title());

            $hash = $step->hash();
            $registeredHash = $step->registeredHash();
            $io->writeln('Built hash: ' . $registeredHash);
            $io->writeln('Current hash: ' . $hash);

            if (!$input->getOption('force') && $hash === $registeredHash) {
                $io->writeln('Hash is already built. So we are skipping this step.');
                continue;
            }

            if ($step->clearBeforeBuild()) {
                $io->writeln('Cleared output directory before building');
            }

            if ($step->build()) {
                $io->success('Successfully built step ' . $step->title());
                $step->registerHash($hash);
            } else {
                $io->error('Building failed for step ' . $step->title());
                $successful = false;
            }
            $sinceStart = $startTime->diff(new \DateTime());
            $io->writeln("Time: " . $sinceStart->format('%i:%s'));
        }

        return $successful ? 0 : 1;
    }

}
