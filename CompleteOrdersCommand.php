<?php

namespace FutureWorld\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

/**
 * Class CompleteOrdersCommand
 * @package FutureWorld\Command
 */
class CompleteOrdersCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:orders:complete')
            ->setDescription('Set "complete" status for orders.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        set_time_limit(0);

        $this->getContainer()->get('app.auto_complete_order')->run($output);
        $this->getContainer()->get('doctrine.orm.default_entity_manager')->flush();

        $output->writeln('Done!');
    }
}
