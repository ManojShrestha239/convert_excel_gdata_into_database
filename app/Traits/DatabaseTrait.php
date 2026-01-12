<?php

namespace App\Traits;

use App\Models\ShadeColor;
use Spatie\SimpleExcel\SimpleExcelReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait DatabaseTrait
{
    private $currentDatabaseName;

    public function getCollection($path, $databaseName)
    {
        $this->ensureConnection();

        $this->createDatabase($databaseName);

        $this->optimizeDatabaseSettings();

        try {
            return DB::transaction(function () use ($path, $databaseName) {
                $this->ensureConnection();

                $rows = SimpleExcelReader::create('storage' . $path)->fromSheetName('Combined Data')->noHeaderRow()->getRows()->toArray();

                $colorant = $this->getColorant($path, $databaseName);

                $headers = $this->getHeaders($rows, $colorant);

                foreach ($colorant as $index => $color) {
                    if (isset($headers[$index])) {
                        $headers[$index] = $color;
                    }
                }

                $data = array_values(array_filter($rows, function ($row, $index) {
                    return $index > 0 && !empty($row[0]);
                }, ARRAY_FILTER_USE_BOTH));

                Log::info("Total rows read: " . count($rows) . ", Data rows (excluding header): " . count($data));

                usort($data, function ($a, $b) {
                    return strcmp($a[1], $b[1]);
                });

                $separateColorName = $this->getSeparateColorName($data);
                $productArray = $this->getSeparateProductArray($data);

                $this->makeFandeckSheet($productArray, $data, $databaseName);
                $this->makeBasecolorSheet($data, $databaseName);
                $fandeck = $this->readFandeck($databaseName);
                $this->makeColorNameSheet($productArray, $separateColorName, $databaseName);
                $this->makeEachProductTable($productArray, $headers, $fandeck, $colorant, $databaseName);

                $productNames = array_keys($this->getSeparateProductArray($rows));
                $this->createProductsTable($databaseName, $productNames);

                return true;
            });
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
        $colorant = SimpleExcelReader::create('storage' . $path)->fromSheetName('Colorant')->noHeaderRow()->getRows()->toArray();
        $colo = [];
        $colorantData = [];

        foreach ($colorant as $index => $color) {
            if ($index == 0) {
                continue;
            }
            $colo[$index + 4] = $color[2];
            $colorantData[] = [
                'colorantname' => $color[1],
                'colorantcode' => $color[2],
                'unitprice' => $color[3],
                'rvalue' => $color[4],
                'gvalue' => $color[5],
                'bvalue' => $color[6],
            ];
        }

        $this->createColorantTable($databaseName);

        $this->insertColorantDataBulk($colorantData, $databaseName);

        return $colo;
    }

    public function getSeparateProductArray($row)
    {
        $result = [];
        foreach ($row as $item) {
            $result[$item[1]][] = $item;
        }
        return $result;
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

        $this->createFandeckTable($products, $databaseName);

        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);
        DB::statement("TRUNCATE TABLE `$sanitizedDbName`.fandecks");

        $fandeckData = [];
        $i = 1;

        foreach ($fandeckArray as $key => $fandeck) {
            $fand = ['id' => $i, 'name' => $key];

            foreach ($products as $index => $array) {
                $check = array_filter($array, function ($item) use ($key) {
                    return $item[0] === $key;
                });
                $value = !empty($check) ? 1 : 0;
                $sanitizedColumnName = $this->sanitizeColumnName($index);
                $sanitizedColumnName = strtolower($sanitizedColumnName);
                $fand["`$sanitizedColumnName`"] = $value;
            }

            $fandeckData[] = $fand;
            $i++;
        }

        $this->insertFandeckData($fandeckData, $databaseName);
    }

    private function createFandeckTable($products, $databaseName)
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);

        DB::statement("DROP TABLE IF EXISTS `$sanitizedDbName`.fandecks");

        $columns = [
            'id INT AUTO_INCREMENT PRIMARY KEY',
            'name VARCHAR(255) NOT NULL'
        ];

        foreach ($products as $index => $array) {
            $sanitizedColumnName = $this->sanitizeColumnName($index);
            $sanitizedColumnName = strtolower($sanitizedColumnName);
            $columns[] = "`$sanitizedColumnName` TINYINT(1) DEFAULT 0";
        }

        $columnsString = implode(', ', $columns);

        DB::statement("CREATE TABLE `$sanitizedDbName`.fandecks ($columnsString)");
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
                DB::statement("INSERT INTO `$sanitizedDbName`.fandecks ($columnsString) VALUES $valuesString", $allValues);
            } catch (\Exception $e) {
                $this->ensureConnection();
                DB::statement("INSERT INTO `$sanitizedDbName`.fandecks ($columnsString) VALUES $valuesString", $allValues);
            }
        }
    }

    public function readFandeck($databaseName)
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);
        $fandecks = DB::select("SELECT id, name FROM `$sanitizedDbName`.fandecks ORDER BY id");
        $fandec = [];

        foreach ($fandecks as $index => $fandeck) {
            $fandec[$index]['id'] = $fandeck->id;
            $fandec[$index]['name'] = $fandeck->name;
        }

        return $fandec;
    }

    public function makeEachProductTable($productArray, $headers, $fandeck, $colorant, $databaseName)
    {
        $colorantKeys = array_keys($colorant);
        $this->productArray = $productArray;
        $keysToUnset = [1, 22, 23, 24, 26, 27, 28];

        foreach ($keysToUnset as $keys) {
            unset($headers[$keys]);
        }
        $headers[0] = "fandeck";
        $headers[2] = "colorName";
        $headers[3] = "colorShade";
        $headers[4] = "base";
        $headers[21] = "bVolume";
        $headers[25] = "formulation";
        $headers[29] = "message";

        $fandeckLookup = [];
        foreach ($fandeck as $key => $fand) {
            $fandeckLookup[$fand['name']] = $fand['id'];
        }

        foreach ($productArray as $index => $products) {
            $this->createProductTable($index, $headers, $colorantKeys, $databaseName);

            $data = [];
            $numericKeys = array_merge($colorantKeys, [21]);

            foreach ($products as $product) {
                foreach ($keysToUnset as $keys) {
                    unset($product[$keys]);
                }

                if (isset($fandeckLookup[$product[0]])) {
                    $product[0] = $fandeckLookup[$product[0]];
                }

                foreach ($numericKeys as $col) {
                    if (isset($product[$col])) {
                        if ($product[$col] == "" || $product[$col] === null) {
                            $product[$col] = 0;
                        } else {
                            $product[$col] = round((float) $product[$col], 2);
                        }
                    }
                }
                $data[] = $product;
            }

            $this->insertProductData($index, $data, $headers, $colorantKeys, $databaseName);

            unset($data);
        }
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
        foreach ($productArray as $index => $product) {
            $headers[] = $index;
        }
        foreach ($data as $index => $item) {
            $array = explode('_', $index);
            $newRow = [];
            foreach ($productArray as $key => $product) {

                $colorName = strtolower(trim($array[0]));
                $colorCode = strtolower(trim($array[1]));

                if (empty($colorName) && !empty($colorCode)) {
                    $array[0] = (string) $colorCode;
                }

                if (empty($colorCode) && !empty($array[0])) {
                    $array[1] = (string) $array[0];
                }

                $newRow['id'] = $id;
                $newRow['colorCode'] = strtoupper($array[1]);
                $newRow['colorName'] = strtoupper($array[0]);
                $newRow['rValue'] = $item[0][26];
                $newRow['gValue'] = $item[0][27];
                $newRow['bValue'] = $item[0][28];
                $check = array_filter($product, function ($ite) use ($array) {
                    return strtolower(trim($ite[3])) === strtolower(trim($array[1]));
                });
                if (!empty($check)) {
                    $value = 1;
                } else {
                    $value = 0;
                }
                $newRow[] = $value;
            }
            $id++;
            $shadeData[] = $newRow;
        }

        $uniqueShadeData = [];
        $seen = [];

        foreach ($shadeData as $shade) {
            $key = strtolower(trim($shade['colorCode']));

            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $uniqueShadeData[] = $shade;
            }
        }
        $shadeData = $uniqueShadeData;

        $this->createColorNameTable($productArray, $databaseName);

        $this->insertColorNameData($shadeData, $headers, $databaseName);
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

    private function createProductTable($productName, $headers, $colorantKeys, $databaseName)
    {
        $tableName = $this->sanitizeTableName($productName);
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);

        DB::statement("DROP TABLE IF EXISTS `$sanitizedDbName`.`$tableName`");

        $columns = ['id INT AUTO_INCREMENT PRIMARY KEY'];

        foreach ($headers as $index => $header) {
            if ($header) {
                $sanitizedColumnName = $this->sanitizeColumnName($header);
                $sanitizedColumnName = strtolower($sanitizedColumnName);

                if (in_array($index, $colorantKeys)) {
                    $sanitizedColumnName = strtoupper($sanitizedColumnName);

                    $columns[] = "`$sanitizedColumnName` DECIMAL(10,2) DEFAULT 0";
                } elseif ($index == 0) {
                    $columns[] = "`$sanitizedColumnName` INT";
                } else {
                    $columns[] = "`$sanitizedColumnName` VARCHAR(255)";
                }
            }
        }

        $columnsString = implode(', ', $columns);
        DB::statement("CREATE TABLE `$sanitizedDbName`.`$tableName` ($columnsString)");
    }

    private function insertProductData($productName, $data, $headers, $colorantKeys, $databaseName)
    {
        $tableName = $this->sanitizeTableName($productName);
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);

        DB::statement("TRUNCATE TABLE `$sanitizedDbName`.`$tableName`");

        if (empty($data)) {
            return;
        }

        $allInsertData = [];
        $columnNames = [];

        foreach ($data as $row) {
            $insertData = [];

            foreach ($headers as $index => $header) {
                if ($header && isset($row[$index])) {
                    if ($row[$index] === null || $row[$index] === '') {
                        switch ($header) {
                            case 'colorShade':
                                if (isset($row[$index - 1]) && !empty($row[$index - 1])) {
                                    $row[$index] = $row[$index - 1];
                                }
                                break;

                            case 'colorName':
                                if (isset($row[$index + 1]) && !empty($row[$index + 1])) {
                                    $row[$index] = $row[$index + 1];
                                }
                                break;
                        }
                    }

                    $sanitizedColumnName = $this->sanitizeColumnName($header);
                    $sanitizedColumnName = strtolower($sanitizedColumnName);

                    $value = $row[$index];

                    if (in_array($index, array_merge($colorantKeys, [21]))) {
                        $value = ($value === "" || $value === null) ? 0 : (float) $value;
                    }

                    $insertData["`$sanitizedColumnName`"] = $value;
                } else {
                    $sanitizedColumnName = $this->sanitizeColumnName($header);
                    $sanitizedColumnName = strtolower($sanitizedColumnName);
                    $insertData["`$sanitizedColumnName`"] = null;
                }
            }

            if (empty($columnNames)) {
                $columnNames = array_keys($insertData);
            }

            $allInsertData[] = array_values($insertData);
        }

        if (empty($allInsertData)) {
            return;
        }

        $chunks = array_chunk($allInsertData, 250);
        $columnsString = implode(', ', $columnNames);

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
                DB::statement("INSERT INTO `$sanitizedDbName`.`$tableName` ($columnsString) VALUES $valuesString", $allValues);
            } catch (\Exception $e) {
                $this->ensureConnection();
                DB::statement("INSERT INTO `$sanitizedDbName`.`$tableName` ($columnsString) VALUES $valuesString", $allValues);
            }
        }
    }

    private function sanitizeTableName($tableName)
    {
        $sanitized = trim($tableName);

        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '_', $sanitized);

        $sanitized = strtolower($sanitized);

        $sanitized = preg_replace('/_+/', '_', $sanitized);

        $sanitized = trim($sanitized, '_');

        if (preg_match('/^[0-9]/', $sanitized)) {
            $sanitized = $sanitized;
        }

        if (empty($sanitized)) {
            $sanitized = 'product_table';
        } else {
            $sanitized = $sanitized;
        }

        return $sanitized;
    }

    private function createColorNameTable($productArray, $databaseName)
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);

        DB::statement("DROP TABLE IF EXISTS `$sanitizedDbName`.shadecolors");

        $columns = [
            'id INT AUTO_INCREMENT PRIMARY KEY',
            'colorcode VARCHAR(255)',
            'colorname VARCHAR(255)',
            'rvalue INT',
            'gvalue INT',
            'bvalue INT'
        ];

        foreach ($productArray as $index => $array) {
            $sanitizedColumnName = $this->sanitizeColumnName($index);
            $sanitizedColumnName = strtolower($sanitizedColumnName);
            $columns[] = "`$sanitizedColumnName` TINYINT(1) DEFAULT 0";
        }

        $columnsString = implode(', ', $columns);
        DB::statement("CREATE TABLE `$sanitizedDbName`.shadecolors ($columnsString)");
    }

    private function insertColorNameData($shadeData, $headers, $databaseName)
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);

        DB::statement("TRUNCATE TABLE `$sanitizedDbName`.shadecolors");

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

            $colorName = strtolower(trim($row['colorName']));
            $colorCode = strtolower(trim($row['colorCode']));

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
                'colorCode' => $colorCode,
                'colorName' => $colorName,
                'rvalue' => (int)($row['rValue']),
                'gvalue' => (int)($row['gValue']),
                'bvalue' => (int)($row['bValue']),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $insertData['colorcode'] = $colorCode ?? '';
            $insertData['colorname'] = $colorName ?? '';
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

        $shadeColorCodes = ShadeColor::whereIn('colorcode', array_column($colorCodes, 0))
            ->pluck('colorcode')
            ->map(fn($code) => strtolower(trim($code)))
            ->toArray();

        $shadeColorCodeMap = array_flip($shadeColorCodes);

        $shadeColorCodeMap = array_change_key_case($shadeColorCodeMap, CASE_LOWER);

        $filteredShadeColors = array_filter(
            $shadeColorDatas,
            static function ($shade) use ($shadeColorCodeMap) {
                $code = strtolower(trim($shade['colorCode']));

                if (isset($shadeColorCodeMap[$code])) {
                    return false;
                }

                return $shade['rvalue'] != 0
                    || $shade['gvalue'] != 0
                    || $shade['bvalue'] != 0;
            }
        );

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
                DB::statement("INSERT INTO `$sanitizedDbName`.shadecolors ($columnsString) VALUES $valuesString", $allValues);
            } catch (\Exception $e) {
                $this->ensureConnection();
                DB::statement("INSERT INTO `$sanitizedDbName`.shadecolors ($columnsString) VALUES $valuesString", $allValues);
            }
        }
    }

    private function createColorantTable($databaseName)
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);

        DB::statement("DROP TABLE IF EXISTS `$sanitizedDbName`.colorants");

        $columns = [
            'id INT AUTO_INCREMENT PRIMARY KEY',
            'colorantName VARCHAR(255) NOT NULL',
            'colorantCode VARCHAR(255) NOT NULL',
            'unitPrice decimal(10, 2) NOT NULL',
            'rvalue int(3) NOT NULL',
            'gvalue int(3) NOT NULL',
            'bvalue int(3) NOT NULL',
        ];

        $columnsString = implode(', ', $columns);
        DB::statement("CREATE TABLE `$sanitizedDbName`.colorants ($columnsString)");
    }

    private function insertColorantDataBulk($colorantData, $databaseName)
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);

        DB::statement("TRUNCATE TABLE `$sanitizedDbName`.colorants");

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
                DB::statement("INSERT INTO `$sanitizedDbName`.colorants ($columnsString) VALUES $valuesString", $allValues);
            } catch (\Exception $e) {
                $this->ensureConnection();
                DB::statement("INSERT INTO `$sanitizedDbName`.colorants ($columnsString) VALUES $valuesString", $allValues);
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
        DB::statement("TRUNCATE TABLE `$sanitizedDbName`.basecolor");

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

        DB::statement("DROP TABLE IF EXISTS `$sanitizedDbName`.basecolor");

        $columns = [
            'id INT AUTO_INCREMENT PRIMARY KEY',
            'base VARCHAR(255) NOT NULL',
            'unitPrice1 decimal(10, 2) NOT NULL',
            'unitPrice2 decimal(10, 2) NOT NULL',
            'unitPrice3 decimal(10, 2) NOT NULL',
            'unitPrice4 decimal(10, 2) NOT NULL',
            'kgLtrFlag int(3) NOT NULL',
        ];

        $columnsString = implode(', ', $columns);
        DB::statement("CREATE TABLE `$sanitizedDbName`.basecolor ($columnsString)");
    }

    private function insertBasecolorData($colorantData, $databaseName)
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);

        DB::statement("TRUNCATE TABLE `$sanitizedDbName`.basecolor");

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
                DB::statement("INSERT INTO `$sanitizedDbName`.basecolor ($columnsString) VALUES $valuesString", $allValues);
            } catch (\Exception $e) {
                $this->ensureConnection();
                DB::statement("INSERT INTO `$sanitizedDbName`.basecolor ($columnsString) VALUES $valuesString", $allValues);
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
                Log::warning('Could not increase timeouts: ' . $e->getMessage());
            }

            try {
                DB::statement('SET SESSION max_allowed_packet=268435456'); // 256MB
            } catch (\Exception $e) {
                Log::warning('Could not increase max_allowed_packet: ' . $e->getMessage());
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::statement('SET AUTOCOMMIT=0');
            DB::statement('SET unique_checks=0');

            try {
                DB::statement('SET sql_log_bin=0');
            } catch (\Exception $e) {
                Log::warning('Could not disable binary logging: ' . $e->getMessage());
            }

            try {
                DB::statement('SET SESSION bulk_insert_buffer_size=67108864'); // 64MB
            } catch (\Exception $e) {
                Log::warning('Could not increase bulk_insert_buffer_size: ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            Log::warning('Database optimization failed: ' . $e->getMessage());
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
            Log::warning('Failed to restore database settings: ' . $e->getMessage());
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
                    Log::info("MySQL $setting: " . $result[0]->Value);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Could not check MySQL configuration: ' . $e->getMessage());
        }
    }

    private function createProductsTable($databaseName, $productNames)
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);

        DB::statement("DROP TABLE IF EXISTS `$sanitizedDbName`.products");

        $columns = [
            'id INT AUTO_INCREMENT PRIMARY KEY',
            'product_name VARCHAR(255) NOT NULL'
        ];

        $columnsString = implode(', ', $columns);

        DB::statement("CREATE TABLE `$sanitizedDbName`.products ($columnsString)");

        $this->insertProductsData($databaseName, $productNames);
    }

    private function insertProductsData($databaseName, $productNames)
    {
        $sanitizedDbName = $this->sanitizeDatabaseName($databaseName);

        if (empty($productNames)) {
            return;
        }

        $productData = array_map(function ($productName) {
            if ($productName === 0 || $productName === 'Product Name') {
                return null;
            }

            $sanitizedProductName = strtolower(str_replace(' ', '_', $productName));

            return ['product_name' => $sanitizedProductName];
        }, $productNames);

        $productData = array_filter($productData);
        usort($productData, function ($a, $b) {
            return strcmp($a['product_name'], $b['product_name']);
        });

        $this->bulkInsert('products', $productData, ['product_name'], $databaseName);
    }
}
