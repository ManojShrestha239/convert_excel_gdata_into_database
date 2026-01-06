<?php

namespace App\Http\Controllers;

use App\Traits\ColorantMapper;
use Illuminate\Http\Request;

class ForDifferentController extends Controller
{
    use ColorantMapper;

    public function index()
    {
        return view('differentExcel');
    }

    public function store(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls|max:10240',
            'database_name' => 'required|string|max:255|regex:/^[a-zA-Z0-9_]+$/',
        ], [
            'excel_file.required' => 'Please select an Excel file to upload.',
            'excel_file.mimes' => 'The file must be an Excel file (.xlsx or .xls).',
            'excel_file.max' => 'The file size must not exceed 10MB.',
            'database_name.required' => 'Database name is required.',
            'database_name.regex' => 'Database name can only contain letters, numbers, and underscores.',
            'database_name.max' => 'Database name must not exceed 255 characters.',
        ]);

        // Increase memory limit for file processing
        ini_set('memory_limit', '2G');
        ini_set('max_execution_time', '300');

        $file = $request->file('excel_file');
        $filePath = $file->getRealPath();

        try {
            return $this->processAndDownloadExcel($filePath, 'processed_colorants.xlsx');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error processing file: ' . $e->getMessage());
        }
    }
}
