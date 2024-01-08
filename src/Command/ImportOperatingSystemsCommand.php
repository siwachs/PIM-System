<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use App\Utils\Utils;
use App\Utils\OperatingSystemStorageMethods;
use App\Exceptions\CustomExceptionMessage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportOperatingSystemsCommand extends Command
{
    private $params;
    const PIMCORE_ASSET_PATH_PARAMETER = 'pimcore_asset_path';

    protected static $defaultName = 'import:operating-systems';

    public function __construct(ParameterBagInterface $params)
    {
        parent::__construct();
        $this->params = $params;
    }

    protected function configure()
    {
        $this->setDescription('Imports operating systems data')
            ->addArgument('file-location', InputArgument::REQUIRED, 'Specify file location')
            ->addArgument('file-name', InputArgument::REQUIRED, 'Specify file name')
            ->addArgument('file-extension', InputArgument::REQUIRED, 'Specify file extension in .ext format')
            ->addArgument('sheet-name', InputArgument::REQUIRED, 'Specify the sheet name')
            ->addArgument('country-code', InputArgument::REQUIRED, 'Specify the Country code');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Importing operating systems data...');
        $fileLocation = $input->getArgument('file-location');
        $fileName = $input->getArgument('file-name');
        $fileExtension = $input->getArgument('file-extension');
        $sheetName = $input->getArgument('sheet-name');
        $countryCode = $input->getArgument('country-code');

        $pimcoreAssetPath = $this->params->get(self::PIMCORE_ASSET_PATH_PARAMETER);

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
            OperatingSystemStorageMethods::storeOperatingSystems($data, $countryCode);

            $output->writeln('Operating systems import completed.');
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
