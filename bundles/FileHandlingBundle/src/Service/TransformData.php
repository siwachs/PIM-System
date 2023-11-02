<?php

namespace FileHandlingBundle\Service;

class TransformData
{
    public function transformProductsCsvToAssocArray($csvFile, $headers)
    {
        $data = [];
        while (($row = fgetcsv($csvFile)) !== false) {
            $rowData = array_combine($headers, $row);
            $data[] = $rowData;
        }

        return $data;
    }
}
