<?php

namespace App\Traits;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Spatie\SimpleExcel\SimpleExcelReader;
use Spatie\SimpleExcel\SimpleExcelWriter;

trait ExcelTrait
{
    public function getCollection($path)
    {
        $data = [];
        $rows = SimpleExcelReader::create('storage' . $path)->fromSheetName('Combined Data')->noHeaderRow()->getRows();
        $headers = $this->getHeaders($rows);
        $colorant = $this->getColorant($rows, $path);
        foreach ($colorant as $index => $color) {
            foreach ($headers as $key => $header) {
                if ($header == $index) {
                    $headers[$key] = $color;
                }
            }
        }
        foreach ($rows as $index => $row) {
            if ($row[0] == "") {
                continue;
            }
            $data[] = $row;
        }

        usort($data, function ($a, $b) {
            return strcmp($a[1], $b[1]);
        });

        $separateColorName = $this->getSeparateColorName($data);
        // dd($separateColorName["Angel Kiss_2162P"]);
        $productArray = $this->getSeparateProductArray($data);
        $this->makeFandeckSheet($productArray, $data);
        $fandeck = $this->readFandeck();
        $this->makeColorNameSheet($productArray, $separateColorName);
        $this->makeEachProductTable($productArray, $headers, $fandeck);
    }

    public function getHeaders($rows)
    {
        $headers = [];
        foreach ($rows as $index => $row) {
            if ($index == 0) {
                foreach ($row as $i) {
                    $headers[] = $i;
                }
            } else {
                break;
            }
        }
        return $headers;
    }

    public function getColorant($rows, $path)
    {
        $colorant = SimpleExcelReader::create('storage' . $path)->fromSheetName('Colorant')->noHeaderRow()->getRows();
        $colo = [];
        foreach ($colorant as $index => $color) {
            if ($index == 0) {
                continue;
            }
            $colo[$color[1]] = $color[2];
        }
        return $colo;
    }

    public function getSeparateProductArray($row)
    {
        $result = array_reduce($row, function ($carry, $item) {
            $carry[$item[1]][] = $item;
            return $carry;
        }, []);
        return $result;
    }

    public function getFandeck($row)
    {
        $result = array_reduce($row, function ($carry, $item) {
            $carry[$item[0]][] = $item;
            return $carry;
        }, []);
        ksort($result);
        return $result;
    }

    public function makeFandeckSheet($products, $data)
    {
        $fandeckArray = $this->getFandeck($data);
        $fandeckHeaders = ['id', 'name'];
        $fandeckData = [];
        foreach ($products as $index => $array) {
            $fandeckHeaders[] = $index;
        }
        $i = 1;
        foreach ($fandeckArray as $key => $fandeck) {
            $fand = [];
            foreach ($products as $index => $array) {
                $fand['id'] = $i;
                $fand['name'] = $key;
                $check = array_filter($array, function ($item) use ($key) {
                    return $item[0] === $key;
                });
                if (!empty($check)) {
                    $value = 1;
                } else {
                    $value = 0;
                }
                $fand[] = $value;
            }
            $i++;
            $fandeckData[$key] = $fand;
        }
        $filepath = storage_path('app/public/excel/fandeck.xlsx');
        // if (Storage::disk('public')->exists("/excel/fandeck.xlsx")) {
        //     Storage::disk('public')->delete("/excel/fandeck.xlsx");
        // }

        if (Storage::disk('public')->exists("excel")) {
            Storage::disk('public')->deleteDirectory("excel");
        }
        SimpleExcelWriter::create($filepath)->addHeader($fandeckHeaders)->addRows($fandeckData);
    }

    public function makeEachProductTable($productArray, $headers, $fandeck)
    {
        $colorantKeys = [5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20];
        $productData = [];
        $this->productArray = $productArray;
        $keysToUnset = [1, 22, 23, 24, 26, 27, 28, 29];
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

        foreach ($productArray as $index => $products) {
            $data = [];
            foreach ($products as $product) {
                foreach ($keysToUnset as $keys) {
                    unset($product[$keys]);
                }
                foreach ($fandeck as $key => $fand) {
                    if (in_array($product[0], $fand)) {
                        $product[0] = $fand['id'];
                    }
                }
                foreach ($colorantKeys as $col) {
                    if ($product[$col] == "") {
                        $product[$col] = 0;
                    }
                    $product[$col] = round((float) $product[$col], 2);
                }
                $data[] = $product;
            }
            $productData[] = $data;
            $filepath = storage_path("app/public/excel/$index.xlsx");
            // if (Storage::disk('public')->exists("/excel/$index.xlsx")) {
            //     Storage::disk('public')->delete("/excel/$index.xlsx");
            // }

            // if (Storage::disk('public')->exists("excel")) {
            //     Storage::disk('public')->deleteDirectory("excel");
            // }

            SimpleExcelWriter::create($filepath)->addHeader($headers)->addRows($data);
        }
    }

    public function readFandeck()
    {
        $fandeck = SimpleExcelReader::create("storage/excel/fandeck.xlsx")->getRows();
        $fandec = [];
        foreach ($fandeck as $index => $fand) {
            $fandec[$index]['id'] = $fand['id'];
            $fandec[$index]['name'] = $fand['name'];
        }
        return $fandec;
    }

    public function getSeparateColorName($row)
    {
        // //old
        // $result = array_reduce($row, function ($carry, $item) {
        //     $carry[$item[2]][] = $item;
        //     return $carry;
        // }, []);
        // dd($result);

        //new
        $result = array_reduce($row, function ($carry, $item) {
            $key = $item[2] . '_' . $item[3];
            if (!isset($carry[$key])) {
                $carry[$key] = [$item];
            }
            return $carry;
        }, []);
        return $result;
    }

    public function makeColorNameSheet($productArray, $data)
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
                $newRow['id'] = $id;
                $newRow['colorCode'] = $array[1];
                $newRow['colorName'] = $array[0];
                $newRow['rValue'] = $item[0][26];
                $newRow['gValue'] = $item[0][27];
                $newRow['bValue'] = $item[0][28];
                $check = array_filter($product, function ($ite) use ($array) {
                    return $ite[2] === $array[0];
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
        $filepath = storage_path('app/public/excel/shadecolor.xlsx');
        // if (Storage::disk('public')->exists("/excel/shadecolor.xlsx")) {
        //     Storage::disk('public')->delete("/excel/shadecolor.xlsx");
        // }

        // if (Storage::disk('public')->exists("excel")) {
        //     Storage::disk('public')->deleteDirectory("excel");
        // }
        SimpleExcelWriter::create($filepath)->addHeader($headers)->addRows($shadeData);
    }
}
