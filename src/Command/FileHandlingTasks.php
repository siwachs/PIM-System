<?php

namespace App\Command;

use App\Service\WriteCsv;
use Pimcore\Model\DataObject;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FileHandlingTasks extends Command
{
    protected static $defaultName = 'app:import-task';
    private $writeCsv;
    public function __construct(WriteCsv $writeCsv)
    {
        parent::__construct();
        $this->writeCsv = new $writeCsv;
    }

    protected function configure()
    {
        $this->setDescription('For File Handling tasks of import or export items from pimcore.')->addArgument('working_directory', InputArgument::REQUIRED, 'From which directory you want to export/import from?')->addArgument('task', InputArgument::REQUIRED, 'You want to import or export');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        //Switch case for Import export.
        $task = $input->getArgument('task');
        $workingDirectory = $input->getArgument('working_directory');

        try {
            switch ($task) {
                case 'import':
                    break;
                case 'export':
                    echo $workingDirectory;
                    if ($workingDirectory === 'products' || $workingDirectory === 'categories') {
                        //throw new \InvalidArgumentException('Invalid working directory currently only products and categories directory available.');
                    }

                    if ($workingDirectory === 'products') {
                        $products = new DataObject\Product\Listing();
                    } else {
                        $categories = new DataObject\Category\Listing();
                        $this->writeCsv->writeCategories($categories);
                    }

                    return Command::SUCCESS;
                default:
                    throw new \InvalidArgumentException('Invalid task you can either import or export a file.');
            }
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
