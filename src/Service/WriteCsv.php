<?php

namespace App\Service;

class WriteCsv
{
    const BASE_PATH = "./UserLogs";
    const CATEGORIES_FILE_PATH = self::BASE_PATH . "/categories.csv";
    const PRODUCTS_FILE_PATH = self::BASE_PATH . "/products.csv";

    public function writeProducts($products)
    {
    }

    public function writeCategories($categories)
    {
        try {
            if (!is_dir(self::BASE_PATH)) {
                mkdir(self::BASE_PATH, 0777, true);
            }
            $file = fopen(self::CATEGORIES_FILE_PATH, 'w');

            fputcsv($file, array_keys(reset($categories)));

            foreach ($categories as $category) {
                fputcsv($file, $category);
            }
            fclose($file);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
