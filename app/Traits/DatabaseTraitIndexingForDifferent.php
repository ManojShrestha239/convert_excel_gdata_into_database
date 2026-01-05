<?php

namespace App\Traits;

use App\Models\ShadeColor;
use Spatie\SimpleExcel\SimpleExcelReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait DatabaseTraitIndexingForDifferent
{
    private $currentDatabaseName;

    /**
     * Attempt to read rows from an Excel file by trying a list of possible sheet names.
     * - Tries each sheet name in order until one succeeds and returns non-empty data
     * - If allowFallbackFirstSheet is true and none of the specified sheets work, falls back to the first sheet
     * - Always reads with noHeaderRow() to be compatible with existing code paths
     *
     * @param string $filePath Relative path starting from storage (e.g., '/app/excel/myfile.xlsx')
     * @param array $sheetCandidates Ordered list of possible sheet names
     * @param bool $allowFallbackFirstSheet Whether to fallback to the first sheet
     * @return array Rows as array of arrays
     * @throws \Exception if no sheet is found or accessible
     */
    private function readExcelRowsFlexible($filePath, array $sheetCandidates, bool $allowFallbackFirstSheet = false)
    {
        $fullPath = 'storage' . $filePath;
        $lastError = null;

        foreach ($sheetCandidates as $name) {
            try {
                $rows = SimpleExcelReader::create($fullPath)
                    ->fromSheetName($name)
                    ->noHeaderRow()
                    ->getRows()
                    ->toArray();
                if (!empty($rows)) {
                    return $rows;
                }
            } catch (\Throwable $e) {
                $lastError = $e;
                // try next candidate
            }
        }

        if ($allowFallbackFirstSheet) {
            try {
                $rows = SimpleExcelReader::create($fullPath)
                    ->noHeaderRow()
                    ->getRows()
                    ->toArray();
                if (!empty($rows)) {
                    return $rows;
                }
            } catch (\Throwable $e) {
                $lastError = $e;
            }
        }

        $sheetListStr = implode(', ', $sheetCandidates);
        $baseMessage = "Unable to read Excel rows. Tried sheets: [$sheetListStr]";
        if ($allowFallbackFirstSheet) {
            $baseMessage .= "; also attempted to fallback to the first sheet.";
        }
        if ($lastError) {
            $baseMessage .= ' Last error: ' . $lastError->getMessage();
        }
        throw new \Exception($baseMessage);
    }

    public function getCollection($path, $databaseName)
    {
        // Validate input parameters
        if (empty($path)) {
            throw new \Exception("File path cannot be empty");
        }

        if (empty($databaseName)) {
            throw new \Exception("Database name cannot be empty");
        }

        if (!file_exists('storage' . $path)) {
            throw new \Exception("Excel file not found at path: storage" . $path);
        }

        $this->ensureConnection();

        $this->createDatabase($databaseName);

        $this->optimizeDatabaseSettings();

        try {
            $this->ensureConnection();

            try {
                // Try multiple likely sheet names to support different shared formats
                $rows = $this->readExcelRowsFlexible(
                    $path,
                    [
                        'Combined Data',
                        'CombinedData',
                        'Data',
                        'Sheet1',
                        'Formulations',
                    ],
                    true // allow fallback to first sheet for the main data
                );
            } catch (\Exception $e) {
                throw new \Exception("Failed to read 'Combined Data' sheet from Excel file: " . $e->getMessage());
            }

            if (empty($rows)) {
                throw new \Exception("Combined Data sheet is empty or contains no valid data");
            }

            $colorantData = $this->getColorant($path, $databaseName);
            $colorant = $colorantData['mapping'];
            $colorantCodes = $colorantData['codes'];

            $headers = $this->getHeaders($rows, $colorant);

            foreach ($colorant as $index => $color) {
                if (isset($headers[$index])) {
                    $headers[$index] = $color;
                }
            }

            $data = array_values(array_filter($rows, function ($row, $index) {
                return $index > 0 && !empty($row[0]);
            }, ARRAY_FILTER_USE_BOTH));

            if (empty($data)) {
                throw new \Exception("No valid data rows found in Combined Data sheet after filtering");
            }

            usort($data, function ($a, $b) {
                return strcmp($a[1], $b[1]);
            });

            $separateColorName = $this->getSeparateColorName($data);
            $productArray = $this->getSeparateProductArray($data);

            if (empty($productArray)) {
                throw new \Exception("No valid products found in the data");
            }

            $this->makeFandeckSheet($productArray, $data, $databaseName);
            $this->makeBasecolorSheet($data, $databaseName);
            $fandeck = $this->readFandeck($databaseName);
            $this->makeColorNameSheet($productArray, $separateColorName, $databaseName);
            $this->makeProductDetailsTable($productArray, $headers, $fandeck, $colorant, $colorantCodes, $databaseName);

            $productArray = $this->getSeparateProductArray($rows);
            $productNames = array_keys($productArray);

            // Create mapping from product names to codes
            $productNameToCodeMapping = $this->createProductNameToCodeMapping($productArray);
            $this->createProductsTable($databaseName, $productNameToCodeMapping);

            return true;
        } catch (\Exception $e) {
            // Re-throw with context
            throw new \Exception("Error processing Excel data: " . $e->getMessage());
        } finally {
            $this->restoreDatabaseSettings();
        }
    }

    public function getHeaders($rows, $colorant)
    {
        $keyValue = 5 + count($colorant);
        $headers = [];
        foreach ($rows as $index => $row) {
            if ($index == 0) {
                foreach ($row as $key => $i) {
                    if ($key > 4 && $key < $keyValue) {
                        $headers[$key] = $colorant[$key];
                    } else {
                        $headers[$key] = $i;
                    }
                }
            } else {
                break;
            }
        }

        return $headers;
    }

    public function getColorant($path, $databaseName)
    {
        try {
            // Try multiple possible sheet names for colorants; do not fallback to first sheet to avoid mixing with data sheet
            $colorant = $this->readExcelRowsFlexible(
                $path,
                [
                    'Colorant',
                    'Colorants',
                    'Colorant List',
                    'ColorantList',
                    'Tint Colorants',
                    'Colorants Master',
                ],
                false
            );
        } catch (\Exception $e) {
            throw new \Exception("Failed to read Colorant sheet from Excel file: " . $e->getMessage());
        }

        if (empty($colorant)) {
            throw new \Exception("Colorant sheet is empty or not found in the Excel file");
        }

        $colo = [];
        $colorantData = [];
        $colorantCodes = []; // Store colorant codes for dynamic column creation

        foreach ($colorant as $index => $color) {
            if ($index == 0) {
                continue;
            }

            // Validate essential colorant data
            if (empty($color[1]) || empty($color[2])) {
                throw new \Exception("Invalid colorant data at row " . ($index + 1) . ". Colorant name and code are required.");
            }

            $colorantCode = trim($color[2]);
            if (empty($colorantCode)) {
                throw new \Exception("Empty colorant code found at row " . ($index + 1) . ". All colorant codes must be non-empty.");
            }

            $colo[$index + 4] = $colorantCode;
            $colorantCodes[] = $colorantCode; // Collect colorant codes
            $colorantData[] = [
                'colorantname' => $color[1],
                'colorantcode' => $colorantCode,
                'unitprice' => $color[3] ?? 0,
                'rvalue' => $color[4] ?? 0,
                'gvalue' => $color[5] ?? 0,
                'bvalue' => $color[6] ?? 0,
            ];
        }

        if (empty($colorantCodes)) {
            throw new \Exception("No valid colorant codes found in the Excel file. At least one colorant is required.");
        }

        $this->createColorantTable($databaseName);
        $this->insertColorantDataBulk($colorantData, $databaseName);

        // Return both the original mapping and the colorant codes
        return [
            'mapping' => $colo,
            'codes' => $colorantCodes
        ];
    }

    public function getSeparateProductArray($row)
    {
        $result = [];
        foreach ($row as $item) {
            $result[$item[1]][] = $item;
        }
        return $result;
    }

    /**
     * Create mapping from product names to product codes
     * Product codes are assigned based on alphabetical order of product names
     */
    private function createProductNameToCodeMapping($products)
    {
        $mapping = [];
        $productNames = array_keys($products);

        // Filter out invalid product names
        $validProductNames = [];
        foreach ($productNames as $name) {
            if (
                $name !== 0 &&
                $name !== '0' &&
                $name !== 1 &&
                $name !== '1' &&
                $name !== 'Product Name' &&
                !empty($name) &&
                $name !== null &&
                trim($name) !== ''
            ) {
                $validProductNames[] = $name;
            }
        }

        // Sort valid product names alphabetically
        sort($validProductNames);

        // Create mapping only for actual valid products (dynamic count)
        foreach ($validProductNames as $index => $productName) {
            $productCode = "product" . ($index + 1);
            $mapping[$productName] = $productCode;
        }

        return $mapping;
    }

    /**
     * Get the number of products available
     */
    public function getNumberOfProducts($products)
    {
        $productNameToCodeMapping = $this->createProductNameToCodeMapping($products);
        return count($productNameToCodeMapping);
    }

    /**
     * Get product information including names and codes
     */
    public function getProductInfo($products)
    {
        $productNameToCodeMapping = $this->createProductNameToCodeMapping($products);
        $productInfo = [];

        foreach ($productNameToCodeMapping as $productName => $productCode) {
            $productInfo[] = [
                'name' => $productName,
                'code' => $productCode,
                'number' => (int)str_replace('product', '', $productCode)
            ];
        }

        return $productInfo;
    }
    public function getFandeck($row)
    {
        $result = [];
        foreach ($row as $item) {
            $result[$item[0]][] = $item;
        }
        ksort($result);
        return $result;
    }

    public function makeFandeckSheet($products, $data, $databaseName)
    {
        $fandeckArray = $this->getFandeck($data);

        // Create mapping from product names to product codes
        $productNameToCodeMapping = $this->createProductNameToCodeMapping($products);

        $this->createFandeckTable($productNameToCodeMapping, $databaseName);

        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);
        DB::statement("TRUNCATE TABLE `$sanitizedDbName`.ms_fandecks");

        $fandeckData = [];
        $i = 1;

        foreach ($fandeckArray as $key => $fandeck) {
            $fand = ['id' => $i, 'name' => $key];

            // Create columns dynamically based on actual product codes
            foreach ($productNameToCodeMapping as $productName => $productCode) {
                $sanitizedColumnName = strtolower($productCode);

                // Check if this fandeck has this product
                $value = 0; // Default to 0

                if (isset($products[$productName])) {
                    $check = array_filter($products[$productName], function ($item) use ($key) {
                        return $item[0] === $key;
                    });
                    if (!empty($check)) {
                        $value = 1;
                    }
                }

                $fand["`$sanitizedColumnName`"] = $value;
            }
            $fandeckData[] = $fand;
            $i++;
        }

        $this->insertFandeckData($fandeckData, $databaseName);
    }

    private function createFandeckTable($productNameToCodeMapping, $databaseName)
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);

        DB::statement("DROP TABLE IF EXISTS `$sanitizedDbName`.ms_fandecks");

        $columns = [
            'id INT AUTO_INCREMENT PRIMARY KEY',
            'name VARCHAR(255) NOT NULL',
            'order_index INT UNSIGNED NOT NULL DEFAULT 0',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ];

        // Create columns dynamically based on actual product codes
        foreach ($productNameToCodeMapping as $productName => $productCode) {
            $sanitizedColumnName = strtolower($productCode);
            $columns[] = "`$sanitizedColumnName` TINYINT(1) DEFAULT 0";
        }

        $columnsString = implode(', ', $columns);

        DB::statement("CREATE TABLE `$sanitizedDbName`.ms_fandecks ($columnsString)");
    }

    private function insertFandeckData($fandeckData, $databaseName)
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);

        if (empty($fandeckData)) {
            return;
        }

        $chunks = array_chunk($fandeckData, 250);

        foreach ($chunks as $chunk) {
            $this->ensureConnection();

            $columns = array_keys($chunk[0]);
            $columnsString = implode(', ', $columns);

            $valueStrings = [];
            $allValues = [];

            foreach ($chunk as $data) {
                $values = array_values($data);
                $valueStrings[] = '(' . str_repeat('?,', count($values) - 1) . '?)';
                $allValues = array_merge($allValues, $values);
            }

            $valuesString = implode(', ', $valueStrings);

            try {
                DB::statement("INSERT INTO `$sanitizedDbName`.ms_fandecks ($columnsString) VALUES $valuesString", $allValues);
            } catch (\Exception $e) {
                $this->ensureConnection();
                DB::statement("INSERT INTO `$sanitizedDbName`.ms_fandecks ($columnsString) VALUES $valuesString", $allValues);
            }
        }
    }

    public function readFandeck($databaseName)
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);
        $fandecks = DB::select("SELECT id, name FROM `$sanitizedDbName`.ms_fandecks ORDER BY id");
        $fandec = [];

        foreach ($fandecks as $index => $fandeck) {
            $fandec[$index]['id'] = $fandeck->id;
            $fandec[$index]['name'] = $fandeck->name;
        }

        return $fandec;
    }

    public function readBasecolor($databaseName)
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);
        $basecolors = DB::select("SELECT id, base FROM `$sanitizedDbName`.ms_basecolor ORDER BY id");
        $baseLookup = [];

        foreach ($basecolors as $basecolor) {
            $baseLookup[$basecolor->base] = $basecolor->id;
        }

        return $baseLookup;
    }

    public function readShadecolors($databaseName)
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);
        $shadecolors = DB::select("SELECT id, colorname, colorcode FROM `$sanitizedDbName`.ms_shadecolors ORDER BY id");
        $shadeLookup = [];

        foreach ($shadecolors as $shade) {
            // Create lookup key by combining colorname and colorcode (similar to how it was created)
            $key = $shade->colorname . '_' . $shade->colorcode;
            $shadeLookup[$key] = $shade->id;
        }

        return $shadeLookup;
    }

    public function readColorantCodes($databaseName)
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);
        $colorants = DB::select("SELECT colorantCode FROM `$sanitizedDbName`.ms_colorants ORDER BY id");
        $colorantCodes = [];

        foreach ($colorants as $colorant) {
            $colorantCodes[] = $colorant->colorantCode;
        }

        return $colorantCodes;
    }

    public function getProductDetailsTableStructure($databaseName)
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);
        $tableName = "ms_product_formulations";

        try {
            $columns = DB::select("DESCRIBE `$sanitizedDbName`.`$tableName`");
            return $columns;
        } catch (\Exception $e) {
            return "Table does not exist or error: " . $e->getMessage();
        }
    }


    public function makeProductDetailsTable($productArray, $headers, $fandeck, $colorant, $colorantCodes, $databaseName)
    {
        // Validate essential parameters
        if (empty($productArray)) {
            throw new \Exception("Product array is empty. Cannot create product details table without products.");
        }

        if (empty($colorantCodes)) {
            throw new \Exception("Colorant codes array is empty. Cannot create dynamic columns without colorant codes.");
        }

        if (empty($fandeck)) {
            throw new \Exception("Fandeck data is empty. Cannot create product details without fandeck information.");
        }

        $colorantKeys = array_keys($colorant);
        $this->productArray = $productArray;
        $keysToUnset = [1, 3, 22, 23, 24, 26, 27, 28];

        foreach ($keysToUnset as $keys) {
            unset($headers[$keys]);
        }

        $fandeckLookup = [];
        foreach ($fandeck as $key => $fand) {
            $fandeckLookup[$fand['name']] = $fand['id'];
        }

        // Create base lookup from basecolor table
        $baseLookup = $this->readBasecolor($databaseName);

        // Create shade lookup from shadecolors table
        $shadeLookup = $this->readShadecolors($databaseName);

        // Create mapping from product names to product codes
        $productNameToCodeMapping = $this->createProductNameToCodeMapping($productArray);

        // Create the single product_details table with dynamic colorant columns
        $this->createProductDetailsTable($databaseName, $colorantCodes);

        $allProductDetailsData = [];

        foreach ($productArray as $productName => $products) {
            if (empty($products)) {
                continue; // Skip empty product arrays
            }

            // Get product code for this product
            $productCode = $productNameToCodeMapping[$productName] ?? "product_" . (array_search($productName, array_keys($productArray)) + 1);

            foreach ($products as $product) {
                if (empty($product) || !is_array($product)) {
                    continue; // Skip invalid product data
                }

                // Capture colorName and colorShade before unsetting keys
                $colorName = $product[2] ?? '';
                $colorShade = $product[3] ?? '';

                $productDetailRow = [];
                $productDetailRow['product_code'] = $productCode;

                // Set fandeck_id
                if (isset($fandeckLookup[$product[0]])) {
                    $productDetailRow['fandeck_id'] = $fandeckLookup[$product[0]];
                } else {
                    throw new \Exception("Empty or invalid fandeck found. Fandeck is required for product: '$productName'");
                }

                // Set shade_id
                if (empty($colorName)) {
                    $colorKey = $colorShade . '_' . $colorShade;
                } elseif (empty($colorShade)) {
                    $colorKey = $colorName . '_' . $colorName;
                } else {
                    $colorKey = $colorName . '_' . $colorShade;
                }

                if (isset($shadeLookup[$colorKey])) {
                    $productDetailRow['shade_id'] = $shadeLookup[$colorKey];
                } else {
                    throw new \Exception("Empty or invalid shade found. Shade color is required for product: '$productName'");
                }

                // Set base_id
                $baseColor = $product[4] ?? '';
                if (!empty($baseColor) && isset($baseLookup[$baseColor])) {
                    $productDetailRow['base_id'] = $baseLookup[$baseColor];
                } else {
                    // $productDetailRow['base_id'] = null;
                    throw new \Exception("Empty or invalid base found. Base color is required for product: '$productName'");
                }

                // Set additional columns
                $productDetailRow['bvolume'] = isset($product[21]) ? round((float) $product[21], 2) : 0;
                $productDetailRow['formulation'] = $product[25] ?? null;
                $productDetailRow['message'] = $product[29] ?? null;

                // Map colorant values to dynamic columns based on actual colorant codes
                $colorantIndex = 0;
                foreach ($colorantKeys as $key) {
                    if ($colorantIndex < count($colorantCodes)) {
                        // Validate colorant code before using it
                        $colorantCode = $colorantCodes[$colorantIndex];
                        if (empty(trim($colorantCode))) {
                            throw new \Exception("Empty or invalid colorant code found at index $colorantIndex");
                        }

                        // Use the actual colorant code as column name (sanitized and uppercase)
                        $columnName = strtoupper($this->sanitizeColumnName($colorantCode));
                        if (empty($columnName)) {
                            throw new \Exception("Failed to sanitize colorant code '$colorantCode' into valid column name");
                        }

                        $value = isset($product[$key]) ? $product[$key] : 0;
                        $value = ($value === "" || $value === null) ? 0 : round((float) $value, 2);
                        $productDetailRow[$columnName] = $value;
                        $colorantIndex++;
                    }
                }

                $allProductDetailsData[] = $productDetailRow;
            }
        }

        if (empty($allProductDetailsData)) {
            throw new \Exception("No valid product details data was generated. Please check your input data.");
        }

        $this->insertProductDetailsData($allProductDetailsData, $databaseName, $colorantCodes);
    }

    private function createProductDetailsTable($databaseName, $colorantCodes = [])
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);

        if (empty($sanitizedDbName)) {
            throw new \Exception("Database name sanitization failed. Cannot create table with invalid database name.");
        }

        DB::statement("DROP TABLE IF EXISTS `$sanitizedDbName`.ms_product_formulations");

        $columns = [
            'id INT AUTO_INCREMENT PRIMARY KEY',
            'product_code VARCHAR(255) NOT NULL',
            'fandeck_id INT NULL',
            'shade_id INT NULL',
            'base_id INT NULL',
            'bvolume DECIMAL(10,2) DEFAULT 0',
            'formulation VARCHAR(255) NULL',
            'message TEXT NULL',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ];

        // Add dynamic colorant columns based on actual colorant codes
        if (!empty($colorantCodes)) {
            foreach ($colorantCodes as $colorantCode) {
                if (empty(trim($colorantCode))) {
                    throw new \Exception("Empty colorant code found while creating table columns. All colorant codes must be non-empty.");
                }

                $sanitizedColumnName = strtoupper($this->sanitizeColumnName($colorantCode));

                if (empty($sanitizedColumnName)) {
                    throw new \Exception("Failed to create valid column name from colorant code: '$colorantCode'");
                }

                // Check for duplicate column names
                $columnExists = false;
                foreach ($columns as $existingColumn) {
                    if (strpos($existingColumn, "`$sanitizedColumnName`") !== false) {
                        throw new \Exception("Duplicate column name '$sanitizedColumnName' detected from colorant code: '$colorantCode'");
                    }
                }

                $columns[] = "`$sanitizedColumnName` DECIMAL(10,2) DEFAULT 0";
            }
        } else {
            // No fallback to hardcoded columns - colorant codes are required
            throw new \Exception("Colorant codes are required to create dynamic table columns. Cannot proceed without valid colorant data from the Excel file.");
        }

        if (count($columns) <= 7) { // Only base columns, no colorant columns added
            throw new \Exception("No colorant columns were added to the table. At least one colorant column is required.");
        }

        $columnsString = implode(', ', $columns);

        try {
            DB::statement("CREATE TABLE `$sanitizedDbName`.ms_product_formulations ($columnsString)");
        } catch (\Exception $e) {
            throw new \Exception("Failed to create product_details table: " . $e->getMessage());
        }
    }

    private function insertProductDetailsData($productDetailsData, $databaseName, $colorantCodes = [])
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);

        DB::statement("TRUNCATE TABLE `$sanitizedDbName`.ms_product_formulations");

        if (empty($productDetailsData)) {
            return;
        }

        $columns = [
            'product_code',
            'fandeck_id',
            'shade_id',
            'base_id',
            'bvolume',
            'formulation',
            'message',
        ];

        // Add dynamic colorant columns based on actual colorant codes
        if (!empty($colorantCodes)) {
            foreach ($colorantCodes as $colorantCode) {
                $sanitizedColumnName = strtoupper($this->sanitizeColumnName($colorantCode));
                $columns[] = $sanitizedColumnName;
            }
        } else {
            // No fallback to hardcoded columns - colorant codes are required
            throw new \Exception("Colorant codes are required for bulk insert operation. Cannot proceed without valid colorant data from the Excel file.");
        }

        $this->bulkInsert('ms_product_formulations', $productDetailsData, $columns, $databaseName);
    }

    private function sanitizeColumnName($columnName)
    {
        $sanitized = trim($columnName);

        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '_', $sanitized);

        $sanitized = preg_replace('/_+/', '_', $sanitized);

        $sanitized = trim($sanitized, '_');

        if (preg_match('/^[0-9]/', $sanitized)) {
            $sanitized = 'col_' . $sanitized;
        }

        if (empty($sanitized)) {
            $sanitized = 'unnamed_column';
        }

        return $sanitized;
    }

    private function createColorNameTable($productNameToCodeMapping, $databaseName)
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);

        DB::statement("DROP TABLE IF EXISTS `$sanitizedDbName`.ms_shadecolors");

        $columns = [
            'id INT AUTO_INCREMENT PRIMARY KEY',
            'colorcode VARCHAR(255)',
            'colorname VARCHAR(255)',
            'rvalue INT',
            'gvalue INT',
            'bvalue INT',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ];

        // Create columns dynamically based on actual product codes
        foreach ($productNameToCodeMapping as $productName => $productCode) {
            $sanitizedColumnName = strtolower($productCode);
            $columns[] = "`$sanitizedColumnName` TINYINT(1) DEFAULT 0";
        }

        $columnsString = implode(', ', $columns);
        DB::statement("CREATE TABLE `$sanitizedDbName`.ms_shadecolors ($columnsString)");
    }

    public function getSeparateColorName($row)
    {
        $result = [];
        foreach ($row as $item) {
            $key = $item[2] . '_' . $item[3];
            if (!isset($result[$key])) {
                $result[$key] = [$item];
            }
        }
        return $result;
    }

    public function makeColorNameSheet($productArray, $data, $databaseName)
    {
        $headers = ['id', 'colorCode', 'colorName', 'rValue', 'gValue', 'bValue'];
        $shadeData = [];
        $id = 1;

        // Create mapping from product names to product codes
        $productNameToCodeMapping = $this->createProductNameToCodeMapping($productArray);

        // Add product codes to headers dynamically
        foreach ($productNameToCodeMapping as $productName => $productCode) {
            $headers[] = $productCode;
        }

        foreach ($data as $index => $item) {
            $array = explode('_', $index);
            $newRow = [];

            // Set basic shade information
            $newRow['id'] = $id;
            $newRow['colorCode'] = $array[1];
            $newRow['colorName'] = $array[0];
            $newRow['rValue'] = $item[0][26];
            $newRow['gValue'] = $item[0][27];
            $newRow['bValue'] = $item[0][28];

            // Check for each actual product
            foreach ($productNameToCodeMapping as $productName => $productCode) {
                $value = 0; // Default to 0

                if (isset($productArray[$productName])) {
                    $check = array_filter($productArray[$productName], function ($ite) use ($array) {
                        return $ite[2] === $array[0];
                    });
                    if (!empty($check)) {
                        $value = 1;
                    }
                }

                $newRow[] = $value;
            }
            $id++;
            $shadeData[] = $newRow;
        }

        $this->createColorNameTable($productNameToCodeMapping, $databaseName);

        $this->insertColorNameData($shadeData, $headers, $databaseName);
    }

    private function insertColorNameData($shadeData, $headers, $databaseName)
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);

        DB::statement("TRUNCATE TABLE `$sanitizedDbName`.ms_shadecolors");

        if (empty($shadeData)) {
            return;
        }

        $shadeColors = ShadeColor::select('colorcode', 'colorname', 'rvalue', 'gvalue', 'bvalue')
            ->get()->keyBy('colorcode')->toArray();

        $allInsertData = [];
        $shadeColorDatas = [];
        $colorCodes = [];

        foreach ($shadeData as $row) {
            $insertData = [];

            $colorName = $row['colorName'];
            $colorCode = $row['colorCode'];

            if (empty($colorName) && !empty($colorCode)) {
                $row['colorName'] = (string) $colorCode;
            }

            if (empty($colorCode) && !empty($row['colorName'])) {
                $row['colorCode'] = (string) $row['colorName'];
            }

            $isOkay = !empty($shadeColors) && isset($shadeColors[$row['colorCode']]);

            $defaults = $isOkay ? $shadeColors[$row['colorCode']] : ['rvalue' => 0, 'gvalue' => 0, 'bvalue' => 0];

            $row['rValue'] = !empty($row['rValue']) ? (int)$row['rValue'] : (int)$defaults['rvalue'];
            $row['gValue'] = !empty($row['gValue']) ? (int)$row['gValue'] : (int)$defaults['gvalue'];
            $row['bValue'] = !empty($row['bValue']) ? (int)$row['bValue'] : (int)$defaults['bvalue'];


            $colorCodes[] = [
                $row['colorCode']
            ];

            $shadeColorDatas[$row['colorCode']] = [
                'colorCode' => $row['colorCode'],
                'colorName' => $row['colorName'],
                'rvalue' => (int)($row['rValue']),
                'gvalue' => (int)($row['gValue']),
                'bvalue' => (int)($row['bValue']),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $insertData['colorcode'] = $row['colorCode'] ?? '';
            $insertData['colorname'] = $row['colorName'] ?? '';
            $insertData['rvalue'] = (int)($row['rValue'] ?? 0);
            $insertData['gvalue'] = (int)($row['gValue'] ?? 0);
            $insertData['bvalue'] = (int)($row['bValue'] ?? 0);

            $productIndex = 0;
            foreach ($headers as $index => $header) {
                if ($index >= 6) {
                    $sanitizedColumnName = $this->sanitizeColumnName($header);
                    $sanitizedColumnName = strtolower($sanitizedColumnName);

                    $value = isset($row[$productIndex]) ? (int)$row[$productIndex] : 0;
                    $insertData["`$sanitizedColumnName`"] = $value;
                    $productIndex++;
                }
            }

            $allInsertData[] = array_values($insertData);
        }

        if (empty($allInsertData)) {
            return;
        }

        if (empty($shadeColorDatas) || empty($colorCodes)) {
            return;
        }

        $shadeColorCodes = ShadeColor::whereIn('colorcode', array_column($colorCodes, 0))->pluck('colorcode')->toArray();

        $filteredShadeColors = array_filter($shadeColorDatas, function ($shade) use ($shadeColorCodes) {
            return !in_array($shade['colorCode'], $shadeColorCodes);
        });

        if (!empty($filteredShadeColors)) {
            ShadeColor::insert(array_values($filteredShadeColors));
        }

        $columnNames = array_keys($insertData ?? []);
        $columnsString = implode(', ', $columnNames);

        $chunks = array_chunk($allInsertData, 250);

        foreach ($chunks as $chunk) {
            $this->ensureConnection();

            $valueStrings = [];
            $allValues = [];

            foreach ($chunk as $values) {
                $valueStrings[] = '(' . str_repeat('?,', count($values) - 1) . '?)';
                $allValues = array_merge($allValues, $values);
            }

            $valuesString = implode(', ', $valueStrings);

            try {
                DB::statement("INSERT INTO `$sanitizedDbName`.ms_shadecolors ($columnsString) VALUES $valuesString", $allValues);
            } catch (\Exception $e) {
                $this->ensureConnection();
                DB::statement("INSERT INTO `$sanitizedDbName`.ms_shadecolors ($columnsString) VALUES $valuesString", $allValues);
            }
        }
    }

    private function createColorantTable($databaseName)
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);

        DB::statement("DROP TABLE IF EXISTS `$sanitizedDbName`.ms_colorants");

        $columns = [
            'id INT AUTO_INCREMENT PRIMARY KEY',
            'colorantName VARCHAR(255) NOT NULL',
            'colorantCode VARCHAR(255) NOT NULL',
            'unitPrice decimal(10, 2) NOT NULL',
            'rvalue int(3) NOT NULL',
            'gvalue int(3) NOT NULL',
            'bvalue int(3) NOT NULL',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ];

        $columnsString = implode(', ', $columns);
        DB::statement("CREATE TABLE `$sanitizedDbName`.ms_colorants ($columnsString)");
    }

    private function insertColorantDataBulk($colorantData, $databaseName)
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);

        DB::statement("TRUNCATE TABLE `$sanitizedDbName`.ms_colorants");

        if (empty($colorantData)) {
            return;
        }

        $chunks = array_chunk($colorantData, 250);
        $columnsString = 'colorantname, colorantcode, unitprice, rvalue, gvalue, bvalue';

        foreach ($chunks as $chunk) {
            $this->ensureConnection();

            $allInsertData = [];
            foreach ($chunk as $data) {
                $allInsertData[] = [
                    $data['colorantname'] ?? '',
                    $data['colorantcode'] ?? '',
                    $data['unitprice'] ?? 0.00,
                    $data['rvalue'] ?? 0,
                    $data['gvalue'] ?? 0,
                    $data['bvalue'] ?? 0
                ];
            }

            $valueStrings = [];
            $allValues = [];

            foreach ($allInsertData as $values) {
                $valueStrings[] = '(?,?,?,?,?,?)';
                $allValues = array_merge($allValues, $values);
            }

            $valuesString = implode(', ', $valueStrings);

            try {
                DB::statement("INSERT INTO `$sanitizedDbName`.ms_colorants ($columnsString) VALUES $valuesString", $allValues);
            } catch (\Exception $e) {
                $this->ensureConnection();
                DB::statement("INSERT INTO `$sanitizedDbName`.ms_colorants ($columnsString) VALUES $valuesString", $allValues);
            }
        }
    }

    private function insertColorantData($colorantData, $databaseName)
    {
        $this->insertColorantDataBulk($colorantData, $databaseName);
    }

    public function getBasecolor($row)
    {
        $result = [];
        foreach ($row as $item) {
            if (isset($item[4]) && !empty($item[4])) {
                $result[$item[4]] = $item[4];
            }
        }
        ksort($result);
        return $result;
    }

    public function makeBasecolorSheet($data, $databaseName)
    {
        $basecolorArray = $this->getBasecolor($data);

        $this->createBasecolorTable($databaseName);

        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);
        DB::statement("TRUNCATE TABLE `$sanitizedDbName`.ms_basecolor");

        $basecolorData = [];

        foreach ($basecolorArray as $base) {
            $basecolorData[] = [
                'base' => $base,
                'unitPrice1' => 0,
                'unitPrice2' => 0,
                'unitPrice3' => 0,
                'unitPrice4' => 0,
                'kgLtrFlag' => 1
            ];
        }

        $this->insertBasecolorData($basecolorData, $databaseName);
    }

    private function createBasecolorTable($databaseName)
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);

        DB::statement("DROP TABLE IF EXISTS `$sanitizedDbName`.ms_basecolor");

        $columns = [
            'id INT AUTO_INCREMENT PRIMARY KEY',
            'base VARCHAR(255) NOT NULL',
            'unitPrice1 decimal(10, 2) NOT NULL',
            'unitPrice2 decimal(10, 2) NOT NULL',
            'unitPrice3 decimal(10, 2) NOT NULL',
            'unitPrice4 decimal(10, 2) NOT NULL',
            'kgLtrFlag int(3) NOT NULL',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ];

        $columnsString = implode(', ', $columns);
        DB::statement("CREATE TABLE `$sanitizedDbName`.ms_basecolor ($columnsString)");
    }

    private function insertBasecolorData($colorantData, $databaseName)
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);

        DB::statement("TRUNCATE TABLE `$sanitizedDbName`.ms_basecolor");

        if (empty($colorantData)) {
            return;
        }

        $chunks = array_chunk($colorantData, 250);
        $columnsString = 'base, unitprice1, unitprice2, unitprice3, unitprice4, kgLtrFlag';

        foreach ($chunks as $chunk) {
            $this->ensureConnection();

            $allInsertData = [];
            foreach ($chunk as $data) {
                $allInsertData[] = [
                    $data['base'] ?? '',
                    $data['unitPrice1'] ?? 0.00,
                    $data['unitPrice2'] ?? 0.00,
                    $data['unitPrice3'] ?? 0.00,
                    $data['unitPrice4'] ?? 0.00,
                    $data['kgLtrFlag'] ?? 1
                ];
            }

            $valueStrings = [];
            $allValues = [];

            foreach ($allInsertData as $values) {
                $valueStrings[] = '(?,?,?,?,?,?)';
                $allValues = array_merge($allValues, $values);
            }

            $valuesString = implode(', ', $valueStrings);

            try {
                DB::statement("INSERT INTO `$sanitizedDbName`.ms_basecolor ($columnsString) VALUES $valuesString", $allValues);
            } catch (\Exception $e) {
                $this->ensureConnection();
                DB::statement("INSERT INTO `$sanitizedDbName`.ms_basecolor ($columnsString) VALUES $valuesString", $allValues);
            }
        }
    }


    private function createDatabase($databaseName)
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);

        $existingDatabases = DB::select("SHOW DATABASES LIKE '$sanitizedDbName'");

        if (!empty($existingDatabases)) {
            DB::statement("DROP DATABASE `$sanitizedDbName`");
        }

        DB::statement("CREATE DATABASE `$sanitizedDbName`
            CHARACTER SET utf8mb4
            COLLATE utf8mb4_unicode_ci");

        $this->currentDatabaseName = $sanitizedDbName;
    }

    private function sanitizeDatabaseName($databaseName)
    {
        if (isset($this->currentDatabaseName)) {
            return $this->currentDatabaseName;
        }

        $sanitized = trim($databaseName);

        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '_', $sanitized);

        $sanitized = strtolower($sanitized);

        $sanitized = preg_replace('/_+/', '_', $sanitized);

        $sanitized = trim($sanitized, '_');

        if (preg_match('/^[0-9]/', $sanitized)) {
            $sanitized = 'db_' . $sanitized;
        }

        if (empty($sanitized)) {
            $sanitized = 'tint_database';
        }

        $sanitized = $sanitized . '_' . date("Y-m-d");

        return $sanitized;
    }

    private function processInChunks($data, $callback, $chunkSize = 500)
    {
        $chunks = array_chunk($data, $chunkSize);
        foreach ($chunks as $chunkIndex => $chunk) {
            if ($chunkIndex % 10 === 0) {
                $this->ensureConnection();
            }

            call_user_func($callback, $chunk);

            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            usleep(1000);
        }
    }

    private function bulkInsert($tableName, $data, $columns, $databaseName, $chunkSize = 500)
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);

        if (empty($data)) {
            return;
        }

        $columnsString = implode(', ', $columns);

        $this->processInChunks($data, function ($chunk) use ($sanitizedDbName, $tableName, $columnsString) {
            $this->ensureConnection();

            $valueStrings = [];
            $allValues = [];

            foreach ($chunk as $row) {
                $values = array_values($row);
                $valueStrings[] = '(' . str_repeat('?,', count($values) - 1) . '?)';
                $allValues = array_merge($allValues, $values);
            }

            $valuesString = implode(', ', $valueStrings);

            try {
                DB::statement("INSERT INTO `$sanitizedDbName`.`$tableName` ($columnsString) VALUES $valuesString", $allValues);
            } catch (\Exception $e) {
                $this->ensureConnection();
                DB::statement("INSERT INTO `$sanitizedDbName`.`$tableName` ($columnsString) VALUES $valuesString", $allValues);
            }
        }, $chunkSize);
    }

    private function optimizeDatabaseSettings()
    {
        try {
            $this->checkMySQLConfiguration();

            try {
                DB::statement('SET SESSION wait_timeout=7200'); // 2 hours
                DB::statement('SET SESSION interactive_timeout=7200'); // 2 hours
            } catch (\Exception $e) {
                return back()->with('error', 'Could not increase interactive_timeout: ' . $e->getMessage());
            }

            try {
                DB::statement('SET SESSION max_allowed_packet=268435456'); // 256MB
            } catch (\Exception $e) {
                return back()->with('error', 'Could not increase max_allowed_packet: ' . $e->getMessage());
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::statement('SET AUTOCOMMIT=0');
            DB::statement('SET unique_checks=0');

            try {
                DB::statement('SET sql_log_bin=0');
            } catch (\Exception $e) {
                return back()->with('error', 'Could not disable binary logging: ' . $e->getMessage());
            }

            try {
                DB::statement('SET SESSION bulk_insert_buffer_size=67108864'); // 64MB
            } catch (\Exception $e) {
                return back()->with('error', 'Could not increase bulk_insert_buffer_size: ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            return back()->with('error', 'Database optimization failed: ' . $e->getMessage());
        }
    }

    private function restoreDatabaseSettings()
    {
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            DB::statement('SET AUTOCOMMIT=1');
            DB::statement('SET unique_checks=1');
            DB::statement('SET sql_log_bin=1');
            DB::statement('COMMIT');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to restore database settings: ' . $e->getMessage());
        }
    }

    private function ensureConnection()
    {
        try {
            DB::select('SELECT 1');
        } catch (\Exception $e) {
            DB::reconnect();
        }
    }

    private function checkMySQLConfiguration()
    {
        try {
            $settings = [
                'max_allowed_packet',
                'wait_timeout',
                'interactive_timeout',
                'innodb_buffer_pool_size',
                'bulk_insert_buffer_size'
            ];

            foreach ($settings as $setting) {
                $result = DB::select("SHOW VARIABLES LIKE '$setting'");
                if (!empty($result)) {
                    return back()->with('error', "MySQL $setting: " . $result[0]->Value);
                }
            }
        } catch (\Exception $e) {
            return back()->with('error', 'Could not check MySQL configuration: ' . $e->getMessage());
        }
    }

    private function createProductsTable($databaseName, $productNameToCodeMapping)
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);

        DB::statement("DROP TABLE IF EXISTS `$sanitizedDbName`.ms_product_details");

        $columns = [
            'id INT AUTO_INCREMENT PRIMARY KEY',
            'code VARCHAR(255) NOT NULL',
            'name VARCHAR(255) NULL',
            'image VARCHAR(255) NULL',
            'area VARCHAR(255) NULL',
            'type VARCHAR(255) NULL',
            'coverageFt2PerGallon DECIMAL(10,2) DEFAULT 0',
            'order_index INT UNSIGNED NOT NULL DEFAULT 0',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ];

        $columnsString = implode(', ', $columns);

        DB::statement("CREATE TABLE `$sanitizedDbName`.ms_product_details ($columnsString)");

        $this->insertProductsData($databaseName, $productNameToCodeMapping);
    }

    private function insertProductsData($databaseName, $productNameToCodeMapping)
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);

        // Extract valid product names from the mapping (exclude placeholders)
        $validProducts = [];
        foreach ($productNameToCodeMapping as $productName => $productCode) {
            $area = null;
            $type = null;

            $lowerName = Str::lower($productName); // Case-insensitive search

            if (Str::contains($lowerName, 'exterior')) {
                $area = 'Exterior';
            } elseif (Str::contains($lowerName, 'interior')) {
                $area = 'Interior';
            }

            if (Str::contains($lowerName, 'emulsion')) {
                $type = 'Emulsion';
            } elseif (Str::contains($lowerName, 'distemper')) {
                $type = 'Distemper';
            }

            $validProducts[] = [
                'code' => $productCode,
                'name' => $productName,
                'image' => null,
                'area' => $area,   // Will be 'Exterior', 'Interior', or null
                'type' => $type,   // Will be 'Emulsion', 'Distemper', or null
            ];
        }

        $this->bulkInsert('ms_product_details', $validProducts, ['code', 'name', 'image', 'area', 'type'], $databaseName);
    }
}
