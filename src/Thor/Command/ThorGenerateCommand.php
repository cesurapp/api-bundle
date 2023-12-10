<?php

namespace Cesurapp\ApiBundle\Thor\Command;

use Cesurapp\ApiBundle\Thor\Extractor\ThorExtractor;
use Cesurapp\ApiBundle\Thor\Generator\TypeScriptGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Thor Generate Api Documentation to JSON File.
 */
#[AsCommand(name: 'thor:extract', description: 'Thor Extract Api Documentation to TS File')]
class ThorGenerateCommand extends Command
{
    public function __construct(private readonly ThorExtractor $extractor)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('path', InputArgument::REQUIRED, 'Files extract path.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!file_exists($input->getArgument('path')) && !mkdir($concurrentDirectory = $input->getArgument('path'), recursive: true) && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }

        // Generate
        $apiData = $this->extractor->extractData(true);
        $tsGenerator = new TypeScriptGenerator($apiData);

        // Copy Custom Directory
        $tsGenerator->generate()->copyFiles($input->getArgument('path'));

        return Command::SUCCESS;
    }
}
