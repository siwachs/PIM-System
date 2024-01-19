<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use App\Utils\Utils;
use App\Utils\PimcoreMailer;
use App\Utils\ProductStorageMethods;
use App\Exceptions\CustomExceptionMessage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportProductsCommand extends Command
{
    private $params;
    private $pimcoreMailer;

    protected static $defaultName = 'import:products';

    public function __construct(
        ParameterBagInterface $params,
        PimcoreMailer $pimcoreMailer
    ) {
        parent::__construct();
        $this->params = $params;
        $this->pimcoreMailer = $pimcoreMailer;
    }

    protected function configure()
    {
        $this->setDescription('Imports products data')
            ->addArgument('file-location', InputArgument::REQUIRED, 'Specify file location')
            ->addArgument('file-name', InputArgument::REQUIRED, 'Specify file name')
            ->addArgument('file-extension', InputArgument::REQUIRED, 'Specify file extension in .ext format')
            ->addArgument('sheet-name', InputArgument::REQUIRED, 'Specify the sheet name')
            ->addArgument('country-code', InputArgument::REQUIRED, 'Specify the Country code');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Importing products data...');
        $pimcoreAssetPath = $this->params->get('pimcore_asset_path');
        $sender = $this->params->get('notification_sender');
        $receiver = $this->params->get('notification_receiver');
        $notificationSubject = $this->params->get('notification_subject');
        $notificationMessage = $this->params->get('notification_message');
        $notificationTemplatePath = $this->params->get('notification_template_path');
        $fileLocation = $input->getArgument('file-location');
        $fileName = $input->getArgument('file-name');
        $fileExtension = $input->getArgument('file-extension');
        $sheetName = $input->getArgument('sheet-name');
        $countryCode = $input->getArgument('country-code');

        if (
            empty($fileLocation)
            || empty($fileName)
            || empty($fileExtension)
            || empty($sheetName)
            || empty($countryCode)
        ) {
            throw new \InvalidArgumentException('File location, name, extension, sheet name,
             and country code must be provided');
        }

        try {
            $excelAsset = Utils::getAsset($fileLocation . $fileName . $fileExtension);
            if ($excelAsset === null) {
                throw new CustomExceptionMessage("Excel Asset not found or not an instance of Asset");
            }

            $excelAssetLocalPath = PIMCORE_PROJECT_ROOT . $pimcoreAssetPath . $excelAsset->getFullPath();

            $spreadsheet = IOFactory::load($excelAssetLocalPath);

            $sheet = $spreadsheet->getSheetByName($sheetName);
            if ($sheet === null) {
                throw new \InvalidArgumentException("Invalid Sheet name.");
            }

            $data = Utils::sheetToAssocArray($sheet);
            ProductStorageMethods::storeProducts($data, $countryCode, $this->params);
            $this->pimcoreMailer->sendMail(
                $sender,
                $receiver,
                $notificationSubject,
                $notificationMessage,
                $notificationTemplatePath
            );

            $output->writeln('Products import completed.');
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
