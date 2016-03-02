<?php

namespace WikiMedia\RelevanceScoring\Console;

use Knp\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportPending extends Command {
    protected function configure() {
        $this->setName('import-pending');
        $this->setDescription('Import results for a search query');
        $this->addArgument('user', InputArgument::OPTIONAL, 'Limit updates to the specified user');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $app = $this->getSilexApplication();
        $username = $input->getArgument('user');
        if ($username === null) {
            $userId = null;
        } else {
            $maybeUser = $app['search.repository.users']->getUserByName($username);
            if ($maybeUser->isEmpty()) {
                $output->writeln("Unknown user");
                return 1;
            }
            $userId = $user->uid;
        }
        list($queries, $results) = $app['search.importer']->importPending(1, $userId);
        $output->writeln("Imported $queries queries with $results results");
    }
}

