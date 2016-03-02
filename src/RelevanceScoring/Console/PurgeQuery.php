<?php

namespace WikiMedia\RelevanceScoring\Console;

use Knp\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PurgeQuery extends Command {
    protected function configure() {
        $this->setName('purge-query');
        $this->setDescription('Deletes (really!) all information about a query and the related scored results');
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

    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $app = $this->getSilexApplication();
        $maybeQueryId = $app['search.repository.queries']->findQueryId(
            $input->getArgument('wiki'),
            $input->getArgument('query')
        );

        if ($maybeQueryId->isEmpty()) {
            $output->writeln("No query could be located.");
            exit(1);
        }

        $queryId = $maybeQueryId->get();
        $numScores = $app['search.repository.scores']->deleteScoresByQueryId($queryId);
        $numResults = $app['search.repository.results']->deleteResultsByQueryId($queryId);
        $app['search.repository.queries']->deleteQueryById($queryId);
        $output->writeln("Succesfuly deleted the query along with $numResults results and $numScores scores");
    }
}

