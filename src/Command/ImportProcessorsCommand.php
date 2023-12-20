<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

use App\Utils\Utils;
use App\Utils\ProcessorStorageMethods;
use App\Exceptions\CustomExceptionMessage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Pimcore\Model\Notification\Service\NotificationService;

class ImportProcessorsCommand extends Command
{
    protected static $defaultName = 'import:processors';
    private $notificationService;
    private $sender;
    private $receiver;

    public function __construct(
        NotificationService $notificationService,
        int $sender,
        int $receiver
    ) {
        parent::__construct();
        $this->notificationService = $notificationService;
        $this->sender = $sender;
        $this->receiver = $receiver;
    }

    protected function configure()
    {
        $this->setDescription('Imports processors data')
            ->addArgument('sheet-name', InputArgument::REQUIRED, 'Specify the sheet name')
            ->addArgument('country-code', InputArgument::REQUIRED, 'Specify the Country code');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Importing processors data...');
        $sheetName = $input->getArgument('sheet-name');
        $countryCode = $input->getArgument('country-code');
        if (empty($sheetName)) {
            throw new \InvalidArgumentException('Sheet name must be provided');
        }
        if (empty($countryCode)) {
            throw new \InvalidArgumentException('Country code must be provided');
        }

        try {
            $excelAsset = Utils::getAsset("/Excel Sheets/Database.xlsx");
            if ($excelAsset === null) {
                throw new CustomExceptionMessage("Excel Asset not found or not an instance of Asset");
            }

            $excelAssetLocalPath = PIMCORE_PROJECT_ROOT . "/public/var/assets" . $excelAsset->getFullPath();

            $spreadsheet = IOFactory::load($excelAssetLocalPath);

            $sheet = $spreadsheet->getSheetByName($sheetName);
            if ($sheet === null) {
                throw new \InvalidArgumentException("Invalid Sheet name.");
            }

            $data = Utils::sheetToAssocArray($sheet);
            ProcessorStorageMethods::storeProcessors($data, $countryCode);
            Utils::sendNotification(
                $this->notificationService,
                $this->sender,
                $this->receiver,
                "From Processors Importer",
                "All processors are imported"
            );

            $output->writeln('Processors import completed.');
            return Command::SUCCESS;
        } catch (CustomExceptionMessage $e) {
            $output->writeln("Error: " . $e->getMessage());
            return Command::FAILURE;
        } catch (\Exception $e) {
            $output->writeln("Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
