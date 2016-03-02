<?php

namespace WikiMedia\RelevanceScoring\Console;

use Knp\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Import extends Command {
    protected function configure() {
        $this->setName('import');
        $this->setDescription('Import results for a search query');
        $this->addArgument(
            'username',
            InputArgument::REQUIRED,
            'The username to attribute import to'
        );
        $this->addArgument(
            'wiki',
            InputArgument::REQUIRED,
            'The wiki to query'
        );
        $this->addArgument(
            'query',
            InputArgument::REQUIRED,
            'The query to import'
        );
        $this->addOption(
            'immediate',
            null,
            InputOption::VALUE_NONE,
            'Import now, rather than marking as pending'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $app = $this->getSilexApplication();
        $username = $input->getArgument('username');
        $maybeUser = $app['search.repository.users']->getUserByName($username);
        if ($maybeUser->isEmpty()) {
            $output->writeln("Could not locate user named $username");
            exit(1);
        }

        $user = $maybeUser->get();
        $wiki = $input->getArgument('wiki');
        $immediate = $input->getOption('immediate');
        $import = $this->makeImporter($immediate, $user, $wiki);

        $query = $input->getArgument('query');
        if ($query === '-') {
            while (!feof(STDIN)) {
                $query = trim(fgets(STDIN));
                if ($query) {
                    $import($query);
                }
            }
        } else {
            $import($query);
        }


        $output->writeln($immediate ? "Imported." : "Inserted pending.");

        return 0;
    }

    private function makeImporter($immediate, $user, $wiki) {
        $app = $this->getSilexApplication();
        if ($immediate) {
            $importer = $app['search.importer'];
            return function ($query) use ($importer, $user, $wiki) {
                $importer->import($user, $wiki, $query);
            };
        } else {
            $repo = $app['search.repository.queries'];
            return function ($query) use ($repo, $user, $wiki) {
                $repo->createQuery($user, $wiki, $query);
            };
        }
    }
}

