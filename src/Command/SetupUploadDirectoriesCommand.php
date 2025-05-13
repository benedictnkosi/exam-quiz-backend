<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:setup-upload-directories',
    description: 'Creates the necessary upload directories for exam papers',
)]
class SetupUploadDirectoriesCommand extends Command
{
    public function __construct(
        private ParameterBagInterface $params
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $uploadDir = $this->params->get('upload_directory');
        $imagesDir = $uploadDir . '/images';

        // Create main upload directory
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
            $output->writeln(sprintf('Created directory: %s', $uploadDir));
        } else {
            $output->writeln(sprintf('Directory already exists: %s', $uploadDir));
        }

        // Create images subdirectory
        if (!file_exists($imagesDir)) {
            mkdir($imagesDir, 0777, true);
            $output->writeln(sprintf('Created directory: %s', $imagesDir));
        } else {
            $output->writeln(sprintf('Directory already exists: %s', $imagesDir));
        }

        $output->writeln('Upload directories setup complete!');
        return Command::SUCCESS;
    }
}