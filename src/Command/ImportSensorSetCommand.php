<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

use App\Utils\Utils;
use App\Utils\SensorSetStorageMethods;
use App\Exceptions\CustomExceptionMessage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Pimcore\Model\Notification\Service\NotificationService;

class ImportSensorSetCommand extends Command
{
    const PIMCORE_ASSET_PATH = '/public/var/assets';

    protected static $defaultName = 'import:sensor-set';
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
        $this->setDescription('Imports sensor set data')
            ->addArgument('file-location', InputArgument::REQUIRED, 'Specify file location')
            ->addArgument('file-name', InputArgument::REQUIRED, 'Specify file name')
            ->addArgument('file-extension', InputArgument::REQUIRED, 'Specify file extension in .ext format')
            ->addArgument('sheet-name', InputArgument::REQUIRED, 'Specify the sheet name')
            ->addArgument('country-code', InputArgument::REQUIRED, 'Specify the Country code');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Importing sensor set data...');
        $fileLocation = $input->getArgument('file-location');
        $fileName = $input->getArgument('file-name');
        $fileExtension = $input->getArgument('file-extension');
        $sheetName = $input->getArgument('sheet-name');
        $countryCode = $input->getArgument('country-code');

        if (empty($fileLocation)) {
            throw new \InvalidArgumentException('File location must be provided');
        }
        if (empty($fileName)) {
            throw new \InvalidArgumentException('File name must be provided');
        }
        if (empty($fileExtension)) {
            throw new \InvalidArgumentException('File extension must be provided');
        }
        if (empty($sheetName)) {
            throw new \InvalidArgumentException('Sheet name must be provided');
        }
        if (empty($countryCode)) {
            throw new \InvalidArgumentException('Country code must be provided');
        }

        try {
            $excelAsset = Utils::getAsset($fileLocation . $fileName . $fileExtension);
            if ($excelAsset === null) {
                throw new CustomExceptionMessage("Excel Asset not found or not an instance of Asset");
            }

            $excelAssetLocalPath = PIMCORE_PROJECT_ROOT . self::PIMCORE_ASSET_PATH . $excelAsset->getFullPath();

            $spreadsheet = IOFactory::load($excelAssetLocalPath);

            $sheet = $spreadsheet->getSheetByName($sheetName);
            if ($sheet === null) {
                throw new \InvalidArgumentException("Invalid Sheet name.");
            }

            $data = Utils::sheetToAssocArray($sheet);
            SensorSetStorageMethods::storeSensorSet($data, $countryCode);

            Utils::sendNotification(
                $this->notificationService,
                $this->sender,
                $this->receiver,
                "From Sensor Set Importer",
                "All sensor set data are imported"
            );

            $output->writeln('Sensor set import completed.');
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
