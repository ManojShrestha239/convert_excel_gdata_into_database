<?php

namespace App\Traits;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

trait ColorantMapper
{
    private ?Spreadsheet $cachedSpreadsheet = null;
    private const CHUNK_SIZE = 500;

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

            $this->cachedSpreadsheet = IOFactory::load($filePath);

            // Get colorant codes from Colorant sheet
            $colorantCodes = $this->getColorantCodes($this->cachedSpreadsheet);

            // Process combined data
            $processedData = $this->processCombinedData($this->cachedSpreadsheet, $colorantCodes);

            return [
                'headers' => $colorantCodes,
                'data' => $processedData,
                'original_headers' => $this->getOriginalHeaders($this->cachedSpreadsheet)
            ];
        } catch (\Exception $e) {
            $this->clearCache();
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

            for ($row = 2; $row <= $highestRow; $row++) {
                $code = $colorantSheet->getCell('C' . $row)->getValue();
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
     * Process Combined Data sheet with chunking to manage memory
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

            // Get header row
            $headerRow = $this->getHeaderRow($combinedSheet, $highestColumn);

            // Pre-build colorant value map
            $colorantValueMap = [];
            for ($row = 2; $row <= $highestRow; $row += self::CHUNK_SIZE) {
                $endRow = min($row + self::CHUNK_SIZE - 1, $highestRow);

                for ($currentRow = $row; $currentRow <= $endRow; $currentRow++) {
                    $rowData = $this->extractRowData($combinedSheet, $currentRow, $headerRow, $highestColumn);
                    $colorantValues = $this->mapColorantQuantities($rowData, $colorantCodes);

                    // Merge colorant values into row data
                    foreach ($colorantValues as $code => $value) {
                        $rowData[$code] = $value;
                    }

                    $processedData[] = $rowData;
                }

                // Garbage collection every chunk
                gc_collect_cycles();
            }

            // Fill missing RGB values
            $processedData = $this->fillMissingRgbValues($processedData);

            return $processedData;
        } catch (\Exception $e) {
            throw new \Exception("Error processing combined data: " . $e->getMessage());
        }
    }

    /**
     * Extract row data from sheet
     *
     * @param $sheet
     * @param int $row
     * @param array $headerRow
     * @param string $highestColumn
     * @return array
     */
    private function extractRowData($sheet, int $row, array $headerRow, string $highestColumn): array
    {
        $rowData = [];
        $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        for ($col = 1; $col <= $highestColIndex; $col++) {
            $header = $headerRow[$col - 1] ?? '';
            $value = $sheet->getCellByColumnAndRow($col, $row)->getValue();
            $rowData[$header] = $value;
        }

        return $rowData;
    }

    /**
     * Get header row from sheet
     *
     * @param $sheet
     * @param string $highestColumn
     * @return array
     */
    private function getHeaderRow($sheet, string $highestColumn): array
    {
        $headerRow = [];
        $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        for ($col = 1; $col <= $highestColIndex; $col++) {
            $cell = $sheet->getCellByColumnAndRow($col, 1)->getValue();
            $headerRow[] = $cell;
        }

        return $headerRow;
    }

    /**
     * Map colorant quantities from row data
     *
     * @param array $rowData
     * @param array $colorantCodes
     * @return array
     */
    private function mapColorantQuantities(array $rowData, array $colorantCodes): array
    {
        $colorantValues = array_fill_keys($colorantCodes, 0);

        for ($i = 1; $i <= 4; $i++) {
            $cntHeader = "CNT{$i}";
            $qntHeader = "QNT{$i}";

            $colorantCode = $rowData[$cntHeader] ?? null;
            $quantity = $rowData[$qntHeader] ?? 0;

            if ($colorantCode && in_array($colorantCode, $colorantCodes)) {
                $colorantValues[$colorantCode] = floatval($quantity);
            }
        }

        return $colorantValues;
    }

    /**
     * Fill missing R, G, B values with values from same ColorCode
     *
     * @param array $processedData
     * @return array
     */
    private function fillMissingRgbValues(array $processedData): array
    {
        $colorCodeRgbMap = [];

        // First pass: collect RGB values
        foreach ($processedData as $row) {
            $colorCode = $this->getColorCodeFromRow($row);

            if (!$colorCode) {
                continue;
            }

            $rgb = $this->getRgbValuesFromRow($row);

            if ($rgb['r'] !== null && $rgb['g'] !== null && $rgb['b'] !== null) {
                $colorCodeRgbMap[$colorCode] = $rgb;
            }
        }

        // Second pass: fill missing values
        foreach ($processedData as &$row) {
            $colorCode = $this->getColorCodeFromRow($row);

            if (!$colorCode || !isset($colorCodeRgbMap[$colorCode])) {
                continue;
            }

            $this->fillRgbInRow($row, $colorCodeRgbMap[$colorCode]);
        }

        return $processedData;
    }

    /**
     * Get ColorCode from row
     *
     * @param array $row
     * @return string|null
     */
    private function getColorCodeFromRow(array $row): ?string
    {
        return $row['ColorCode'] ?? $row['colorCode'] ?? $row['colorcode'] ?? null;
    }

    /**
     * Get RGB values from row
     *
     * @param array $row
     * @return array
     */
    private function getRgbValuesFromRow(array $row): array
    {
        $r = $row['R'] ?? $row['r'] ?? $row['rValue'] ?? $row['rvalue'] ?? null;
        $g = $row['G'] ?? $row['g'] ?? $row['gValue'] ?? $row['gvalue'] ?? null;
        $b = $row['B'] ?? $row['b'] ?? $row['bValue'] ?? $row['bvalue'] ?? null;

        return [
            'r' => !$this->isEmptyValue($r) ? $r : null,
            'g' => !$this->isEmptyValue($g) ? $g : null,
            'b' => !$this->isEmptyValue($b) ? $b : null,
        ];
    }

    /**
     * Fill RGB values in row
     *
     * @param array $row
     * @param array $rgbValues
     * @return void
     */
    private function fillRgbInRow(array &$row, array $rgbValues): void
    {
        $rKey = $this->findKey($row, ['R', 'r', 'rValue', 'rvalue']);
        if ($rKey !== null && $this->isEmptyValue($row[$rKey])) {
            $row[$rKey] = $rgbValues['r'];
        }

        $gKey = $this->findKey($row, ['G', 'g', 'gValue', 'gvalue']);
        if ($gKey !== null && $this->isEmptyValue($row[$gKey])) {
            $row[$gKey] = $rgbValues['g'];
        }

        $bKey = $this->findKey($row, ['B', 'b', 'bValue', 'bvalue']);
        if ($bKey !== null && $this->isEmptyValue($row[$bKey])) {
            $row[$bKey] = $rgbValues['b'];
        }
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
            $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

            for ($col = 1; $col <= $highestColIndex; $col++) {
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
     * @param string $outputFileName
     * @return void
     */
    public function downloadTransformedExcel(array $processedData, string $outputFileName = 'transformed_data.xlsx'): void
    {
        try {
            if (empty($processedData)) {
                throw new \Exception('No data to export.');
            }

            if (!$this->cachedSpreadsheet) {
                throw new \Exception('Spreadsheet not loaded.');
            }

            // Create new spreadsheet for output
            $newSpreadsheet = new Spreadsheet();
            $newSheet = $newSpreadsheet->getActiveSheet();

            // Get headers
            $originalHeaders = $this->getOriginalHeaders($this->cachedSpreadsheet);
            $colorantCodes = $this->getColorantCodes($this->cachedSpreadsheet);
            $allHeaders = array_merge($originalHeaders, $colorantCodes);

            // Write headers
            $col = 1;
            foreach ($allHeaders as $header) {
                $newSheet->setCellValueByColumnAndRow($col, 1, $header);
                $col++;
            }

            // Write data in chunks
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

            $this->clearCache();
            exit;
        } catch (\Exception $e) {
            $this->clearCache();
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

            $this->downloadTransformedExcel($processed['data'], $outputFileName);
        } catch (\Exception $e) {
            $this->clearCache();
            throw new \Exception("Error processing and downloading Excel: " . $e->getMessage());
        }
    }

    /**
     * Clear cached spreadsheet to free memory
     *
     * @return void
     */
    private function clearCache(): void
    {
        if ($this->cachedSpreadsheet) {
            $this->cachedSpreadsheet->disconnectWorksheets();
            $this->cachedSpreadsheet = null;
            gc_collect_cycles();
        }
    }
}
