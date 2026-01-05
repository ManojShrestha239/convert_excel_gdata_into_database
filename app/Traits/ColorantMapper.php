<?php

namespace App\Traits;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

trait ColorantMapper
{
    /**
     * Process Excel file and map colorant codes
     *
     * @param string $filePath
     * @return array
     */
    public function processColorantMapping(string $filePath): array
    {
        try {
            if (!file_exists($filePath)) {
                throw new \Exception("File not found: {$filePath}");
            }

            $spreadsheet = IOFactory::load($filePath);

            // Get colorant codes from Colorant sheet
            $colorantCodes = $this->getColorantCodes($spreadsheet);

            // Process combined data
            $processedData = $this->processCombinedData($spreadsheet, $colorantCodes);

            return [
                'headers' => $colorantCodes,
                'data' => $processedData,
                'original_headers' => $this->getOriginalHeaders($spreadsheet)
            ];
        } catch (\Exception $e) {
            throw new \Exception("Error processing colorant mapping: " . $e->getMessage());
        }
    }

    /**
     * Extract unique colorant codes from Colorant sheet
     *
     * @param \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet
     * @return array
     */
    private function getColorantCodes(Spreadsheet $spreadsheet): array
    {
        try {
            $colorantSheet = $spreadsheet->getSheetByName('Colorant');

            if (!$colorantSheet) {
                throw new \Exception('Sheet "Colorant" not found in the Excel file.');
            }

            $colorantCodes = [];

            $highestRow = $colorantSheet->getHighestRow();

            if ($highestRow < 2) {
                throw new \Exception('Colorant sheet is empty or has insufficient data.');
            }

            // Start from row 2 to skip header (assuming row 1 is header)
            for ($row = 2; $row <= $highestRow; $row++) {
                $code = $colorantSheet->getCell('C' . $row)->getValue(); // Column C is colorantcode
                if (!empty($code) && !in_array($code, $colorantCodes)) {
                    $colorantCodes[] = $code;
                }
            }

            if (empty($colorantCodes)) {
                throw new \Exception('No colorant codes found in the Colorant sheet.');
            }

            return $colorantCodes;
        } catch (\Exception $e) {
            throw new \Exception("Error extracting colorant codes: " . $e->getMessage());
        }
    }

    /**
     * Process Combined Data sheet and map colorant quantities
     *
     * @param \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet
     * @param array $colorantCodes
     * @return array
     */
    private function processCombinedData(Spreadsheet $spreadsheet, array $colorantCodes): array
    {
        try {
            $combinedSheet = $spreadsheet->getSheetByName('Combined Data');

            if (!$combinedSheet) {
                throw new \Exception('Sheet "Combined Data" not found in the Excel file.');
            }

            $processedData = [];

            $highestRow = $combinedSheet->getHighestRow();
            $highestColumn = $combinedSheet->getHighestColumn();

            if ($highestRow < 2) {
                throw new \Exception('Combined Data sheet is empty or has insufficient data.');
            }

            // Get header row (assuming row 1 is header)
            $headerRow = [];
            for ($col = 1; $col <= \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn); $col++) {
                $cell = $combinedSheet->getCellByColumnAndRow($col, 1)->getValue();
                $headerRow[] = $cell;
            }

            // Process data rows (start from row 2)
            for ($row = 2; $row <= $highestRow; $row++) {
                $rowData = [];
                $colorantValues = array_fill_keys($colorantCodes, 0);

                // Get original row data
                for ($col = 1; $col <= \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn); $col++) {
                    $header = $headerRow[$col - 1] ?? '';
                    $value = $combinedSheet->getCellByColumnAndRow($col, $row)->getValue();
                    $rowData[$header] = $value;
                }

                // Map colorant quantities
                for ($i = 1; $i <= 4; $i++) {
                    $cntHeader = "CNT{$i}";
                    $qntHeader = "QNT{$i}";

                    $colorantCode = $rowData[$cntHeader] ?? null;
                    $quantity = $rowData[$qntHeader] ?? 0;

                    if ($colorantCode && in_array($colorantCode, $colorantCodes)) {
                        $colorantValues[$colorantCode] = floatval($quantity);
                    }
                }

                // Add colorant values to row data
                foreach ($colorantValues as $code => $value) {
                    $rowData[$code] = $value;
                }

                $processedData[] = $rowData;
            }

            // Fill missing RGB values with same ColorCode values
            $processedData = $this->fillMissingRgbValues($processedData);

            return $processedData;
        } catch (\Exception $e) {
            throw new \Exception("Error processing combined data: " . $e->getMessage());
        }
    }

    /**
     * Fill missing R, G, B values with values from same ColorCode
     *
     * @param array $processedData
     * @return array
     */
    private function fillMissingRgbValues(array $processedData): array
    {
        // Create a lookup map of ColorCode to RGB values
        $colorCodeRgbMap = [];

        // First pass: collect RGB values for each ColorCode where they exist
        foreach ($processedData as $row) {
            $colorCode = $row['ColorCode'] ?? $row['colorCode'] ?? $row['colorcode'] ?? null;

            if (!$colorCode) {
                continue;
            }

            $r = $row['R'] ?? $row['r'] ?? $row['rValue'] ?? $row['rvalue'] ?? null;
            $g = $row['G'] ?? $row['g'] ?? $row['gValue'] ?? $row['gvalue'] ?? null;
            $b = $row['B'] ?? $row['b'] ?? $row['bValue'] ?? $row['bvalue'] ?? null;

            // Only store if all RGB values are present and not empty
            if (!$this->isEmptyValue($r) && !$this->isEmptyValue($g) && !$this->isEmptyValue($b)) {
                $colorCodeRgbMap[$colorCode] = [
                    'r' => $r,
                    'g' => $g,
                    'b' => $b
                ];
            }
        }

        // Second pass: fill missing RGB values
        foreach ($processedData as &$row) {
            $colorCode = $row['ColorCode'] ?? $row['colorCode'] ?? $row['colorcode'] ?? null;

            if (!$colorCode || !isset($colorCodeRgbMap[$colorCode])) {
                continue;
            }

            // Check for R value
            $rKey = $this->findKey($row, ['R', 'r', 'rValue', 'rvalue']);
            if ($rKey !== null && $this->isEmptyValue($row[$rKey])) {
                $row[$rKey] = $colorCodeRgbMap[$colorCode]['r'];
            }

            // Check for G value
            $gKey = $this->findKey($row, ['G', 'g', 'gValue', 'gvalue']);
            if ($gKey !== null && $this->isEmptyValue($row[$gKey])) {
                $row[$gKey] = $colorCodeRgbMap[$colorCode]['g'];
            }

            // Check for B value
            $bKey = $this->findKey($row, ['B', 'b', 'bValue', 'bvalue']);
            if ($bKey !== null && $this->isEmptyValue($row[$bKey])) {
                $row[$bKey] = $colorCodeRgbMap[$colorCode]['b'];
            }
        }

        return $processedData;
    }

    /**
     * Check if a value is empty or null
     *
     * @param mixed $value
     * @return bool
     */
    private function isEmptyValue($value): bool
    {
        return $value === null || $value === '' || $value === 0 || $value === '0';
    }

    /**
     * Find a key in an array from a list of possible keys
     *
     * @param array $array
     * @param array $possibleKeys
     * @return string|null
     */
    private function findKey(array $array, array $possibleKeys): ?string
    {
        foreach ($possibleKeys as $key) {
            if (array_key_exists($key, $array)) {
                return $key;
            }
        }
        return null;
    }

    /**
     * Get original headers from Combined Data sheet
     *
     * @param \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet
     * @return array
     */
    private function getOriginalHeaders(Spreadsheet $spreadsheet): array
    {
        try {
            $combinedSheet = $spreadsheet->getSheetByName('Combined Data');

            if (!$combinedSheet) {
                throw new \Exception('Sheet "Combined Data" not found in the Excel file.');
            }

            $headers = [];

            $highestColumn = $combinedSheet->getHighestColumn();

            for ($col = 1; $col <= \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn); $col++) {
                $cell = $combinedSheet->getCellByColumnAndRow($col, 1)->getValue();
                $headers[] = $cell;
            }

            if (empty($headers)) {
                throw new \Exception('No headers found in Combined Data sheet.');
            }

            return $headers;
        } catch (\Exception $e) {
            throw new \Exception("Error extracting headers: " . $e->getMessage());
        }
    }

    /**
     * Generate and download transformed Excel file
     *
     * @param array $processedData
     * @param string $originalFilePath
     * @param string $outputFileName
     * @return void
     */
    public function downloadTransformedExcel(array $processedData, string $originalFilePath, string $outputFileName = 'transformed_data.xlsx'): void
    {
        try {
            if (!file_exists($originalFilePath)) {
                throw new \Exception("Original file not found: {$originalFilePath}");
            }

            if (empty($processedData)) {
                throw new \Exception('No data to export.');
            }

            $spreadsheet = IOFactory::load($originalFilePath);
            $combinedSheet = $spreadsheet->getSheetByName('Combined Data');

            if (!$combinedSheet) {
                throw new \Exception('Sheet "Combined Data" not found in the Excel file.');
            }

            // Create new spreadsheet for output
            $newSpreadsheet = new Spreadsheet();
            $newSheet = $newSpreadsheet->getActiveSheet();

            // Get all headers (original + colorant codes)
            $originalHeaders = $this->getOriginalHeaders($spreadsheet);
            $colorantCodes = $this->getColorantCodes($spreadsheet);
            $allHeaders = array_merge($originalHeaders, $colorantCodes);

            // Write headers
            $col = 1;
            foreach ($allHeaders as $header) {
                $newSheet->setCellValueByColumnAndRow($col, 1, $header);
                $col++;
            }

            // Write data
            $row = 2;
            foreach ($processedData as $data) {
                $col = 1;
                foreach ($allHeaders as $header) {
                    $value = $data[$header] ?? '';
                    $newSheet->setCellValueByColumnAndRow($col, $row, $value);
                    $col++;
                }
                $row++;
            }

            // Download the file
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $outputFileName . '"');
            header('Cache-Control: max-age=0');

            $writer = new Xlsx($newSpreadsheet);
            $writer->save('php://output');
            exit;
        } catch (\Exception $e) {
            throw new \Exception("Error downloading transformed Excel: " . $e->getMessage());
        }
    }

    /**
     * Alternative: Return transformed data as collection
     *
     * @param array $processedData
     * @return \Illuminate\Support\Collection
     */
    public function getTransformedCollection(array $processedData): \Illuminate\Support\Collection
    {
        try {
            if (empty($processedData)) {
                throw new \Exception('No data to transform into collection.');
            }

            return collect($processedData);
        } catch (\Exception $e) {
            throw new \Exception("Error creating transformed collection: " . $e->getMessage());
        }
    }

    /**
     * Complete processing method with download
     *
     * @param string $filePath
     * @param string $outputFileName
     * @return void
     */
    public function processAndDownloadExcel(string $filePath, string $outputFileName = 'transformed_colorant_data.xlsx'): void
    {
        try {
            if (!file_exists($filePath)) {
                throw new \Exception("File not found: {$filePath}");
            }

            $processed = $this->processColorantMapping($filePath);

            if (!isset($processed['data']) || empty($processed['data'])) {
                throw new \Exception('No processed data available for download.');
            }

            $this->downloadTransformedExcel($processed['data'], $filePath, $outputFileName);
        } catch (\Exception $e) {
            throw new \Exception("Error processing and downloading Excel: " . $e->getMessage());
        }
    }
}
