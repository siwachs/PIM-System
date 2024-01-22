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
use Pimcore\Translation\Translator;

class ImportProductsCommand extends Command
{
    const HOME_PATH = '/';
    private $params;
    private $pimcoreMailer;
    private $adminTranslation;

    protected static $defaultName = 'import:products';

    public function __construct(
        ParameterBagInterface $params,
        PimcoreMailer $pimcoreMailer,
        Translator $adminTranslation
    ) {
        parent::__construct();
        $this->params = $params;
        $this->pimcoreMailer = $pimcoreMailer;
        $this->adminTranslation = $adminTranslation;
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
        $fileLocation = $input->getArgument('file-location');
        $fileName = $input->getArgument('file-name');
        $fileExtension = $input->getArgument('file-extension');
        $sheetName = $input->getArgument('sheet-name');
        $countryCode = $input->getArgument('country-code');
        $sender = $this->params->get('notification_sender');
        $receiver = $this->params->get('notification_receiver');
        $notificationSubject = $this->params->get('notification_subject');
        $notificationMessage = $this->params->get('notification_message');
        $notificationTemplatePath = self::HOME_PATH . $countryCode . $this->params->get('notification_template_path');
        $this->adminTranslation->setLocale($countryCode);

        // Admin Translation Keys
        $invalidArgs = $this->params->get('invalid_args');
        $fileNotFound = $this->params->get('file_not_found');
        $invalidSheetName = $this->params->get('invalid_sheet_name');
        $productImportCompleted = $this->params->get('product_import_complete');

        if (
            empty($fileLocation)
            || empty($fileName)
            || empty($fileExtension)
            || empty($sheetName)
            || empty($countryCode)
        ) {
            $errorMessage = $this->adminTranslation->trans($invalidArgs);
            throw new \InvalidArgumentException($errorMessage);
        }

        try {
            $excelAsset = Utils::getAsset($fileLocation . $fileName . $fileExtension);
            if ($excelAsset === null) {
                $errorMessage = $this->adminTranslation->trans($fileNotFound);
                throw new CustomExceptionMessage($errorMessage);
            }

            $excelAssetLocalPath = PIMCORE_PROJECT_ROOT . $pimcoreAssetPath . $excelAsset->getFullPath();

            $spreadsheet = IOFactory::load($excelAssetLocalPath);

            $sheet = $spreadsheet->getSheetByName($sheetName);
            if ($sheet === null) {
                $errorMessage = $this->adminTranslation->trans($invalidSheetName);
                throw new \InvalidArgumentException($errorMessage);
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

            $successMessage = $this->adminTranslation->trans($productImportCompleted);
            $output->writeln($successMessage);
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
